<?php

namespace App\Services\PackageCheckout;

use Carbon\CarbonImmutable;

final class PackageOffers
{
    public function get(string $code): array
    {
        $offer = config('package_checkout.offers.'.$code);
        abort_unless(preg_match('/^[a-z]{1,32}$/D', $code) && is_array($offer), 404);
        abort_unless(is_int($offer['amount_minor'] ?? null) && $offer['amount_minor'] >= 100
            && $offer['amount_minor'] <= 100000000 && is_string($offer['name'] ?? null), 503);

        return ['code' => $code, 'name' => $offer['name'], 'amount_minor' => $offer['amount_minor'],
            'currency' => 'PHP', 'quantity' => 1, 'version' => config('package_checkout.version')];
    }

    public function endsAt(string $code): ?CarbonImmutable
    {
        return $code === 'pickleball' ? CarbonImmutable::parse(config('package_checkout.pickleball_ends_at')) : null;
    }

    public function available(string $code): bool
    {
        $deadline = $this->endsAt($code);

        return $deadline === null || now()->lt($deadline->subSeconds(60));
    }
}
