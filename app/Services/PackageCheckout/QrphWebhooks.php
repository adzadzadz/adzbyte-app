<?php

namespace App\Services\PackageCheckout;

use App\Models\PackageCheckout;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class QrphWebhooks
{
    public function __construct(private QrphClient $provider) {}

    public function receive(string $body): void
    {
        try {
            $event = json_decode($body, true, 32, JSON_THROW_ON_ERROR)['data'] ?? null;
        } catch (\JsonException) {
            abort(422);
        }
        abort_unless(is_array($event) && $this->provider->id($event['id'] ?? null, 'evt') && ($event['type'] ?? null) === 'event', 422);
        $attributes = $event['attributes'] ?? null;
        abort_unless(is_array($attributes) && ($attributes['livemode'] ?? null) === $this->provider->live()
            && is_string($attributes['type'] ?? null) && strlen($attributes['type']) <= 100
            && preg_match('/^[a-z_]+(?:\.[a-z_]+){1,4}$/D', $attributes['type'])
            && is_int($attributes['created_at'] ?? null) && $attributes['created_at'] > 0
            && $attributes['created_at'] <= now()->timestamp + 300 && is_array($attributes['data'] ?? null), 422);
        $hash = hash('sha256', $body);
        if ($this->replayed($event['id'], $hash)) {
            return;
        }

        $resource = $attributes['data'];
        $type = $attributes['type'];
        $checkout = null;
        $intent = null;
        if (in_array($type, ['payment.paid', 'payment.failed'], true)) {
            abort_unless(($resource['type'] ?? null) === 'payment' && $this->provider->id($resource['id'] ?? null, 'pay'), 422);
            $intent = $resource['attributes']['payment_intent_id'] ?? null;
            abort_unless($this->provider->id($intent, 'pi'), 422);
            $checkout = PackageCheckout::query()->where('provider_intent_id', $intent)->first();
            if (! $checkout) {
                // Unknown create recovery uses authoritative PI metadata, never event metadata alone.
                $checkout = $this->recover($intent);
            }
        } elseif ($type === 'qrph.expired') {
            // Native signed expiry identifies the QRPh source and its durable PI.
            // Each checkout creates one method for that intent; never recover or
            // create provider resources from an unmatched expiry notification.
            if (($resource['type'] ?? null) === 'qrph' && $this->provider->id($resource['id'] ?? null, 'qrph')
                && ($resource['attributes']['livemode'] ?? null) === $this->provider->live()
                && ($resource['attributes']['source_status'] ?? null) === 'expired'
                && $this->provider->id($resource['attributes']['payment_intent_id'] ?? null, 'pi')) {
                $intent = $resource['attributes']['payment_intent_id'];
                $checkout = PackageCheckout::query()->where('provider_intent_id', $intent)->first();
            } elseif (($resource['type'] ?? null) === 'payment_method' && $this->provider->id($resource['id'] ?? null, 'pm')
                && ($resource['attributes']['type'] ?? null) === 'qrph'
                && ($resource['attributes']['livemode'] ?? null) === $this->provider->live()) {
                $checkout = PackageCheckout::query()->where('provider_method_id', $resource['id'])->first();
            }
        }

        try {
            DB::transaction(function () use ($event, $hash, $type, $attributes, $resource, $checkout, $intent): void {
                if ($this->replayed($event['id'], $hash)) {
                    return;
                }
                $locked = $checkout ? PackageCheckout::query()->lockForUpdate()->find($checkout->id) : null;
                $outcome = 'unmatched';
                if ($locked && $locked->provider_mode === ($attributes['livemode'] ? 'live' : 'test')) {
                    if ($intent !== null && $locked->provider_intent_id === null && $locked->creation_phase === 'intent_reserved') {
                        $locked->forceFill(['provider_intent_id' => $intent])->save();
                    }
                    if ($type === 'qrph.expired' && ($intent === null || $locked->provider_intent_id === $intent)) {
                        $outcome = 'expired';
                        if ($locked->status !== 'paid') {
                            $locked->forceFill(['status' => 'expired', 'qr_image' => null])->save();
                        }
                    } elseif ($locked->provider_intent_id === $intent && $this->validPayment($locked, $resource, $type)) {
                        if ($type === 'payment.paid') {
                            if ($locked->status === 'paid') {
                                $outcome = $locked->provider_payment_id === $resource['id'] ? 'already_paid' : 'payment_conflict';
                            } else {
                                // A genuine settlement wins even after local/provider QR expiry.
                                $locked->forceFill(['status' => 'paid', 'qr_image' => null,
                                    'provider_payment_id' => $resource['id'],
                                    'paid_at' => CarbonImmutable::createFromTimestampUTC($resource['attributes']['paid_at']),
                                ])->save();
                                $outcome = 'paid';
                            }
                        } else {
                            $outcome = 'failed';
                            if ($locked->status !== 'paid') {
                                $locked->forceFill(['status' => 'failed', 'qr_image' => null])->save();
                            }
                        }
                    } else {
                        $outcome = 'rejected';
                    }
                }
                // No raw payload, customer/billing fields, provider tokens or image stored in receipts.
                DB::table('package_qrph_events')->insert(['id' => $event['id'], 'payload_hash' => $hash,
                    'event_type' => $type, 'outcome' => $outcome, 'package_checkout_id' => $locked?->id,
                    'created_at' => now(), 'updated_at' => now()]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Concurrent deliveries race on the event primary key. Both effects roll back together.
            abort_unless($this->replayed($event['id'], $hash), 503);
        }
    }

    private function validPayment(PackageCheckout $checkout, array $payment, string $type): bool
    {
        $values = $payment['attributes'] ?? [];

        return ($values['amount'] ?? null) === $checkout->amount_minor
            && ($values['currency'] ?? null) === $checkout->currency
            && ($values['livemode'] ?? null) === ($checkout->provider_mode === 'live')
            && ($values['source']['type'] ?? null) === 'qrph'
            && ($values['status'] ?? null) === ($type === 'payment.paid' ? 'paid' : 'failed')
            && ($type !== 'payment.paid' || (is_int($values['paid_at'] ?? null)
                && $values['paid_at'] > 0 && $values['paid_at'] <= now()->timestamp + 300));
    }

    private function recover(string $intent): ?PackageCheckout
    {
        try {
            $data = $this->provider->retrieve($intent);
            $reference = $data['attributes']['metadata']['package_checkout_reference'] ?? null;
            $checkout = is_string($reference) ? PackageCheckout::query()->find($reference) : null;
            if (! $checkout || $checkout->provider_intent_id !== null || $checkout->creation_phase !== 'intent_reserved'
                || ! in_array($checkout->status, ['creating', 'unknown'], true) || ($data['id'] ?? null) !== $intent) {
                return null;
            }
            $this->provider->assertIntent($data, $checkout);

            return $checkout;
        } catch (QrphFailure) {
            // Retry delivery later; do not permanently receipt a temporary read failure.
            abort(503);
        }
    }

    private function replayed(string $id, string $hash): bool
    {
        $existing = DB::table('package_qrph_events')->where('id', $id)->value('payload_hash');
        if ($existing !== null) {
            abort_unless(hash_equals($existing, $hash), 409);

            return true;
        }

        return false;
    }
}
