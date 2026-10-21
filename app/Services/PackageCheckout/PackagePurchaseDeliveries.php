<?php

namespace App\Services\PackageCheckout;

use App\Jobs\DeliverPackagePurchase;
use App\Models\PackageCheckout;
use App\Models\PackagePurchaseDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class PackagePurchaseDeliveries
{
    private const ENDPOINT = 'https://wp.adzbyte.com/wp-json/adzbyte/v1/package-purchase';

    public function enqueue(PackageCheckout $checkout, string $phase): void
    {
        if (! in_array($phase, ['pending', 'completed'], true) || DB::transactionLevel() === 0) {
            throw new LogicException('Purchases must be queued with their checkout transaction.');
        }
        $completed = $phase === 'completed';
        if ($completed && ($checkout->status !== 'paid' || $checkout->provider_payment_id === null || $checkout->paid_at === null)) {
            throw new LogicException('Only a verified paid purchase can be queued.');
        }
        $payload = json_encode([
            'reference' => $checkout->id, 'phase' => $phase, 'payment_reference' => $completed ? $checkout->provider_payment_id : null,
            'full_name' => $checkout->contactName(), 'email' => $checkout->email, 'phone' => $checkout->phone,
            'package_code' => $checkout->package_code, 'package_name' => $checkout->package_name,
            'amount_minor' => $checkout->amount_minor, 'currency' => $checkout->currency, 'quantity' => $checkout->quantity,
            'paid_at' => $completed ? $checkout->paid_at->utc()->format('Y-m-d\TH:i:s\Z') : null, 'test_mode' => $checkout->provider_mode === 'test',
        ], JSON_THROW_ON_ERROR);
        PackagePurchaseDelivery::query()->create(['package_checkout_id' => $checkout->id, 'phase' => $phase, 'payload' => $payload,
            'payload_hash' => hash('sha256', $payload), 'status' => 'pending', 'next_attempt_at' => now()]);
        DB::afterCommit(fn () => DeliverPackagePurchase::dispatchAfterResponse($checkout->id, $phase));
    }

    public function deliver(int $limit = 25): int
    {
        if (! $this->ready()) {
            return 0;
        }
        $count = 0;
        $started = microtime(true);
        // Leave 35 seconds for a final pair of bounded SMTP deliveries within
        // the approximately 45-second command budget.
        while ($count < min(100, max(1, $limit)) && microtime(true) - $started < 10) {
            $delivery = $this->claim();
            if (! $delivery) {
                break;
            }
            [$status, $error] = $this->send($delivery, config('package_checkout.bridge_token'));
            $this->complete($delivery, $status, $error);
            $count++;
        }

        return $count;
    }

    public function deliverReference(string $reference, string $phase): void
    {
        if (! $this->ready()) {
            return;
        }
        $delivery = $this->claim($reference, $phase);
        if ($delivery) {
            [$status, $error] = $this->send($delivery, config('package_checkout.bridge_token'));
            $this->complete($delivery, $status, $error);
        }
    }

    private function ready(): bool
    {
        if (config('package_checkout.bridge_enabled') !== true) {
            return false;
        }
        $token = config('package_checkout.bridge_token');
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $token)) {
            throw new LogicException('Purchase bridge credentials are not configured.');
        }

        return true;
    }

    public function retry(string $reference, string $phase): bool
    {
        return DB::transaction(function () use ($reference, $phase): bool {
            $delivery = PackagePurchaseDelivery::query()->where('package_checkout_id', $reference)->where('phase', $phase)->lockForUpdate()->first();
            if (! $delivery || $delivery->status !== 'needs_review') {
                return false;
            }
            // Operator retry preserves total attempts and the original immutable payload.
            $delivery->forceFill(['status' => 'pending', 'next_attempt_at' => now(), 'claim_token' => null, 'claimed_at' => null])->save();

            return true;
        }, 3);
    }

    private function claim(?string $reference = null, ?string $phase = null): ?PackagePurchaseDelivery
    {
        return DB::transaction(function () use ($reference, $phase): ?PackagePurchaseDelivery {
            $delivery = PackagePurchaseDelivery::query()->when($reference !== null, fn ($query) => $query->where('package_checkout_id', $reference)->where('phase', $phase))
                ->where(function ($query): void {
                    $query->where(function ($pending): void {
                        $pending->where('status', 'pending')->where(function ($due): void {
                            $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                        });
                    })->orWhere(function ($abandoned): void {
                        $abandoned->where('status', 'processing')->where('claimed_at', '<=', now()->subMinutes(5));
                    });
                })->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if (! $delivery) {
                return null;
            }
            $delivery->forceFill(['status' => 'processing', 'attempts' => $delivery->attempts + 1,
                'claim_token' => (string) Str::uuid(), 'claimed_at' => now(), 'next_attempt_at' => null])->save();

            return $delivery;
        }, 3);
    }

    private function send(PackagePurchaseDelivery $delivery, string $token): array
    {
        try {
            $body = $delivery->payload;
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            if (! hash_equals($delivery->payload_hash, hash('sha256', $body))
                || ($payload['reference'] ?? null) !== $delivery->package_checkout_id || ($payload['phase'] ?? null) !== $delivery->phase) {
                return ['needs_review', 'payload_invalid'];
            }
            $testEmails = array_map('strtolower', config('package_checkout.bridge_test_emails', []));
            if (($payload['test_mode'] ?? null) === true && (config('package_checkout.bridge_test_enabled') !== true
                || ! in_array(strtolower($payload['email']), $testEmails, true))) {
                return ['needs_review', 'test_delivery_blocked'];
            }
            // Every retry sends identical bytes with the receipt reference as the
            // bridge's durable idempotency key. Never retry inside this HTTP call.
            $response = Http::withToken($token)->acceptJson()->withBody($body, 'application/json')
                ->withoutRedirecting()->connectTimeout(5)->timeout(35)
                ->withHeader('Idempotency-Key', $delivery->package_checkout_id)->send('POST', self::ENDPOINT);
            if ($response->status() === 409) {
                return ['needs_review', 'bridge_conflict'];
            }
            if (in_array($response->status(), [401, 403], true)) {
                return ['needs_review', 'bridge_auth'];
            }
            if (in_array($response->status(), [400, 422], true)) {
                return ['needs_review', 'bridge_rejected'];
            }
            if (! in_array($response->status(), [200, 202], true)) {
                return ['pending', 'bridge_unavailable'];
            }
            if (strlen($response->body()) > 4096 || $response->json('data.reference') !== $delivery->package_checkout_id) {
                return ['pending', 'bridge_response_invalid'];
            }
            $status = $response->json('data.status');
            if ($status === 'review') {
                return ['needs_review', 'bridge_review'];
            }
            if (($status === 'captured' && $delivery->phase === 'pending'
                || $status === 'delivered' && $delivery->phase === 'completed') && $response->status() === 200) {
                return ['delivered', null];
            }

            return $status === 'pending' && $response->status() === 202
                ? ['pending', 'bridge_pending'] : ['pending', 'bridge_response_invalid'];
        } catch (ConnectionException) {
            return ['pending', 'transport_unknown'];
        } catch (Throwable) {
            // Never log/chain customer details, bridge credentials or raw responses.
            return ['needs_review', 'local_delivery_error'];
        }
    }

    private function complete(PackagePurchaseDelivery $delivery, string $status, ?string $error): void
    {
        DB::transaction(function () use ($delivery, $status, $error): void {
            $locked = PackagePurchaseDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($locked->status !== 'processing' || $locked->claim_token !== $delivery->claim_token) {
                return;
            }
            if ($status === 'pending' && $locked->attempts >= 10) {
                $status = 'needs_review';
            }
            $locked->forceFill(['status' => $status, 'last_error_code' => $error,
                'claim_token' => null, 'claimed_at' => null,
                'next_attempt_at' => $status === 'pending' ? now()->addSeconds(min(3600, 60 * (2 ** min(6, $locked->attempts - 1)))) : null,
                'delivered_at' => $status === 'delivered' ? now() : null,
            ])->save();
        }, 3);
    }
}
