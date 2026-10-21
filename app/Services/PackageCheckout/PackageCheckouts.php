<?php

namespace App\Services\PackageCheckout;

use App\Models\PackageCheckout;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PackageCheckouts
{
    public function __construct(private PackageOffers $offers, private QrphClient $provider, private PackagePurchaseDeliveries $purchases) {}

    public function create(array $input, string $proof): array
    {
        $name = $input['full_name'] ?? $input['business_name'];
        $phone = $input['phone'] ?? null;
        $base = ['package_code' => $input['package_code'], 'offer_version' => $input['offer_version'], 'operation_id' => $input['operation_id']];
        $hash = hash('sha256', json_encode([...$base, 'full_name' => $name, 'email' => $input['email'], 'phone' => $phone], JSON_THROW_ON_ERROR));
        // Preserve already-created operations from the deployed business_name contract.
        $legacyHash = $phone === null ? hash('sha256', json_encode([...$base, 'business_name' => $name, 'email' => $input['email']], JSON_THROW_ON_ERROR)) : null;
        $proofHash = hash('sha256', $proof);
        $this->provider->ready();
        try {
            [$checkout, $owner] = DB::transaction(function () use ($input, $hash, $legacyHash, $proofHash, $name, $phone): array {
                $existing = PackageCheckout::query()->where('operation_id', $input['operation_id'])->lockForUpdate()->first();
                if ($existing) {
                    return [$this->replay($existing, $hash, $proofHash, $legacyHash), false];
                }
                $offer = $this->offers->get($input['package_code']);
                abort_unless($this->offers->available($offer['code']) && $offer['version'] === $input['offer_version'], 409, 'Offer unavailable.');

                $checkout = PackageCheckout::query()->create([
                    'operation_id' => $input['operation_id'], 'request_hash' => $hash, 'proof_hash' => $proofHash,
                    'package_code' => $offer['code'], 'package_name' => $offer['name'], 'amount_minor' => $offer['amount_minor'],
                    'currency' => $offer['currency'], 'quantity' => 1, 'offer_version' => $offer['version'],
                    'business_name' => $name, 'full_name' => $name, 'email' => $input['email'], 'phone' => $phone,
                    'provider_mode' => $this->provider->live() ? 'live' : 'test',
                    'status' => 'creating', 'creation_phase' => 'intent_reserved',
                ]);
                $this->purchases->enqueue($checkout, 'pending');

                return [$checkout, true];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // The winning insert owns provider creation. A competing retry only reads it.
            $checkout = PackageCheckout::query()->where('operation_id', $input['operation_id'])->firstOrFail();
            $this->replay($checkout, $hash, $proofHash, $legacyHash);
            $owner = false;
        }

        if ($owner) {
            $this->generate($checkout);
        }

        return [$this->status($checkout->fresh()), $owner];
    }

    public function read(string $reference, string $proof): PackageCheckout
    {
        $checkout = PackageCheckout::query()->findOrFail($reference);
        abort_unless(hash_equals($checkout->proof_hash, hash('sha256', $proof)), 404);

        return $this->status($checkout);
    }

    public function status(PackageCheckout $checkout): PackageCheckout
    {
        return DB::transaction(function () use ($checkout): PackageCheckout {
            $locked = PackageCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($locked->status === 'pending' && $locked->expires_at?->isPast()) {
                $locked->forceFill(['status' => 'expired', 'qr_image' => null])->save();
            } elseif ($locked->status === 'creating' && $locked->created_at->lt(now()->subMinutes(2))) {
                // A killed worker cannot safely resume a POST, even with an idempotency header.
                $locked->forceFill(['status' => 'unknown', 'qr_image' => null])->save();
            }

            return $locked;
        }, 3);
    }

    private function replay(PackageCheckout $checkout, string $hash, string $proofHash, ?string $legacyHash): PackageCheckout
    {
        $matches = hash_equals($checkout->request_hash, $hash)
            || ($legacyHash !== null && $checkout->phone === null && hash_equals($checkout->request_hash, $legacyHash));
        abort_unless($matches && hash_equals($checkout->proof_hash, $proofHash), 409, 'Operation conflict.');

        return $checkout;
    }

    private function generate(PackageCheckout $checkout): void
    {
        try {
            $intent = $this->provider->createIntent($checkout);
            if (! $this->advance($checkout, ['provider_intent_id' => $intent['id'], 'creation_phase' => 'method_reserved'])) {
                return;
            }
            $deadline = $this->offers->endsAt($checkout->package_code);
            $seconds = min(config('package_checkout.qr_seconds'), $deadline === null
                ? config('package_checkout.qr_seconds') : (int) now()->diffInSeconds($deadline, false));
            if ($seconds < 60 || $seconds > 9000) {
                $this->advance($checkout, ['status' => 'expired', 'qr_image' => null]);

                return;
            }
            // Capture a conservative deadline before method creation, not after transport latency.
            $expiresAt = now()->addSeconds($seconds);
            $method = $this->provider->createMethod($seconds);
            if (! $this->advance($checkout, ['provider_method_id' => $method, 'creation_phase' => 'attach_reserved', 'expires_at' => $expiresAt])) {
                return;
            }
            $image = $this->provider->attach($checkout);
            $this->advance($checkout, ['status' => 'pending', 'creation_phase' => 'complete', 'qr_image' => $image]);
        } catch (QrphFailure) {
            $this->advance($checkout, ['status' => 'unknown', 'qr_image' => null]);
        }
    }

    private function advance(PackageCheckout $checkout, array $changes): bool
    {
        return DB::transaction(function () use ($checkout, $changes): bool {
            $locked = PackageCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($locked->status !== 'creating') {
                return false;
            }
            if (isset($changes['provider_intent_id']) && $locked->provider_intent_id !== null
                && $locked->provider_intent_id !== $changes['provider_intent_id']) {
                throw new QrphFailure;
            }
            $locked->forceFill($changes)->save();
            $checkout->setRawAttributes($locked->getAttributes(), true);

            return true;
        }, 3);
    }
}
