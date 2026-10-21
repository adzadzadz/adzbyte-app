<?php

namespace App\Services\PackageCheckout;

use App\Models\PackageCheckout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class QrphClient
{
    public function live(): bool
    {
        $mode = config('package_checkout.mode');
        abort_unless(in_array($mode, ['test', 'live'], true), 503);

        return $mode === 'live';
    }

    public function ready(): void
    {
        $prefix = $this->live() ? 'sk_live_' : 'sk_test_';
        $secret = config('package_checkout.secret_key');
        abort_unless(is_string($secret) && preg_match('/^'.$prefix.'[A-Za-z0-9]{8,200}$/D', $secret), 503);
        abort_unless(is_string(config('package_checkout.webhook_secret')) && strlen(config('package_checkout.webhook_secret')) >= 16, 503);
    }

    public function createIntent(PackageCheckout $checkout): array
    {
        $intent = $this->request('POST', '/v1/payment_intents', ['data' => ['attributes' => [
            'amount' => $checkout->amount_minor, 'currency' => $checkout->currency,
            'payment_method_allowed' => ['qrph'], 'description' => 'Package checkout '.$checkout->id,
            'metadata' => $this->metadata($checkout),
        ]]]);
        $this->assertIntent($intent, $checkout);
        if (($intent['attributes']['status'] ?? null) !== 'awaiting_payment_method') {
            throw new QrphFailure;
        }

        return $intent;
    }

    public function createMethod(int $seconds): string
    {
        $method = $this->request('POST', '/v1/payment_methods', ['data' => ['attributes' => [
            'type' => 'qrph', 'expiry_seconds' => $seconds,
        ]]]);
        if (! $this->id($method['id'] ?? null, 'pm') || ($method['type'] ?? null) !== 'payment_method'
            || ($method['attributes']['type'] ?? null) !== 'qrph'
            || ($method['attributes']['livemode'] ?? null) !== $this->live()) {
            throw new QrphFailure;
        }

        return $method['id'];
    }

    public function attach(PackageCheckout $checkout): string
    {
        $intent = $this->request('POST', '/v1/payment_intents/'.$checkout->provider_intent_id.'/attach', [
            'data' => ['attributes' => ['payment_method' => $checkout->provider_method_id]],
        ]);
        $this->assertIntent($intent, $checkout);
        $image = $intent['attributes']['next_action']['code']['image_url'] ?? null;
        if ($intent['id'] !== $checkout->provider_intent_id || ($intent['attributes']['status'] ?? null) !== 'awaiting_next_action'
            || ! is_string($image) || strlen($image) > 350000
            || ! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/D', $image, $parts)) {
            throw new QrphFailure;
        }
        $bytes = base64_decode($parts[1], true);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n") || $size === false
            || $size[2] !== IMAGETYPE_PNG || $size[0] < 1 || $size[1] < 1 || $size[0] > 2048 || $size[1] > 2048) {
            throw new QrphFailure;
        }

        return $image;
    }

    public function retrieve(string $id): array
    {
        if (! $this->id($id, 'pi')) {
            throw new QrphFailure;
        }

        return $this->request('GET', '/v1/payment_intents/'.$id);
    }

    public function assertIntent(array $intent, PackageCheckout $checkout): void
    {
        $attributes = $intent['attributes'] ?? [];
        if (! $this->id($intent['id'] ?? null, 'pi') || ($intent['type'] ?? null) !== 'payment_intent'
            || ($attributes['amount'] ?? null) !== $checkout->amount_minor || ($attributes['currency'] ?? null) !== $checkout->currency
            || ($attributes['livemode'] ?? null) !== ($checkout->provider_mode === 'live')
            || ($attributes['payment_method_allowed'] ?? null) !== ['qrph']) {
            throw new QrphFailure;
        }
        foreach ($this->metadata($checkout) as $key => $value) {
            if (($attributes['metadata'][$key] ?? null) !== $value) {
                throw new QrphFailure;
            }
        }
    }

    public function id(mixed $id, string $prefix): bool
    {
        return is_string($id) && preg_match('/^'.$prefix.'_[A-Za-z0-9]{1,96}$/D', $id) === 1;
    }

    private function metadata(PackageCheckout $checkout): array
    {
        return ['package_checkout_reference' => $checkout->id, 'operation_id' => $checkout->operation_id,
            'offer_version' => $checkout->offer_version];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $this->ready();
        try {
            $request = Http::baseUrl('https://api.paymongo.com')->withBasicAuth(config('package_checkout.secret_key'), '')
                ->acceptJson()->asJson()->withoutRedirecting()->connectTimeout(5)->timeout(12);
            // No automatic POST retries: the reservation owner sends every step once.
            $response = $method === 'GET' ? $request->get($path) : $request->post($path, $payload);
        } catch (ConnectionException) {
            throw new QrphFailure;
        }
        if (! $response->successful() || strlen($response->body()) > 500000 || ! is_array($response->json('data'))) {
            throw new QrphFailure;
        }

        return $response->json('data');
    }
}
