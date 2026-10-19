<?php

namespace App\Services\Payments;

use App\Exceptions\PayMongoFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class PayMongoClient
{
    public function ready(): void
    {
        // Live activation requires its own explicitly qualified release gate.
        abort_unless(config('services.paymongo.enabled') === true && config('services.paymongo.mode') === 'test'
            && str_starts_with((string) config('services.paymongo.secret_key'), 'sk_test_') && ! app()->isProduction(), 503);
    }

    public function methods(): array
    {
        $data = $this->request('GET', '/v1/merchants/capabilities/payment_methods');
        if (! array_is_list($data) || array_filter($data, fn ($item) => ! is_string($item)) !== []) {
            throw new PayMongoFailure('invalid_response');
        }

        return array_values(array_intersect(config('services.paymongo.methods', []), $data));
    }

    public function create(array $payload, string $key): string
    {
        // The actual sandbox endpoint ignored identical Idempotency-Key values.
        // Creation is dispatched once by the durable attempt reservation owner.
        $data = $this->request('POST', '/v2/checkout_sessions', $payload, $key);
        if (($data['data']['type'] ?? null) !== 'checkout_session' || ! is_string($data['data']['id'] ?? null)
            || ! preg_match('/^cs_[A-Za-z0-9]+$/D', $data['data']['id'])) {
            throw new PayMongoFailure('invalid_response');
        }

        return $data['data']['id'];
    }

    public function retrieve(string $id): array
    {
        if (! preg_match('/^cs_[A-Za-z0-9]+$/D', $id)) {
            throw new PayMongoFailure('invalid_reference');
        }

        return $this->request('GET', '/v1/checkout_sessions/'.$id);
    }

    public function expire(string $id): void
    {
        if (! preg_match('/^cs_[A-Za-z0-9]+$/D', $id)) {
            throw new PayMongoFailure('invalid_reference');
        }
        $this->request('POST', '/v1/checkout_sessions/'.$id.'/expire');
    }

    private function pending(): PendingRequest
    {
        $this->ready();

        return Http::baseUrl('https://api.paymongo.com')->withBasicAuth(config('services.paymongo.secret_key'), '')
            ->acceptJson()->asJson()->withoutRedirecting()->connectTimeout(5)->timeout(15);
    }

    private function request(string $method, string $path, array $body = [], ?string $key = null): array
    {
        try {
            $request = $this->pending();
            if ($key !== null) {
                $request = $request->withHeader('Idempotency-Key', $key);
            }
            $response = $method === 'GET' ? $request->get($path) : $request->post($path, $body);
        } catch (ConnectionException) {
            // Never chain raw transport/provider messages or authenticated headers.
            throw new PayMongoFailure('transport_unknown');
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new PayMongoFailure('provider_response');
        }

        return $response->json();
    }
}
