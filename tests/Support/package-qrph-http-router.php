<?php

use App\Models\PackageCheckout;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Explicit loopback-only synthetic integration fixture. Never a public entrypoint.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing'
    || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || ! preg_match('#^/private/tmp/adzbyte-package-http-[A-Za-z0-9_-]+\.sqlite$#D', getenv('DB_DATABASE') ?: '')
    || getenv('DB_CONNECTION') !== 'sqlite') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config(['package_checkout.enabled' => true, 'package_checkout.api_token' => 'synthetic-package-integration-token-only',
    'package_checkout.mode' => 'test', 'package_checkout.secret_key' => 'sk_test_synthetic123456',
    'package_checkout.webhook_secret' => 'whsk_synthetic_webhook_secret_only',
    'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'array']);
Http::preventStrayRequests();
Http::fake(function ($request) {
    if ($request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/payment_intents') {
        return Http::response(['data' => ['id' => 'pi_'.Str::ulid(),
            'type' => 'payment_intent', 'attributes' => [...$request['data']['attributes'],
                'status' => 'awaiting_payment_method', 'livemode' => false, 'client_key' => 'client_synthetic_not_public']]]);
    }
    if ($request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/payment_methods') {
        return Http::response(['data' => ['id' => 'pm_'.Str::ulid(),
            'type' => 'payment_method', 'attributes' => ['type' => 'qrph', 'livemode' => false]]]);
    }
    if ($request->method() === 'POST' && preg_match('#^https://api.paymongo.com/v1/payment_intents/(pi_[A-Za-z0-9]+)/attach$#D', $request->url(), $match)) {
        $checkout = PackageCheckout::query()->where('provider_intent_id', $match[1])->firstOrFail();

        return Http::response(['data' => ['id' => $match[1], 'type' => 'payment_intent', 'attributes' => [
            'amount' => $checkout->amount_minor, 'currency' => $checkout->currency, 'payment_method_allowed' => ['qrph'],
            'livemode' => false, 'status' => 'awaiting_next_action', 'metadata' => ['package_checkout_reference' => $checkout->id,
                'operation_id' => $checkout->operation_id, 'offer_version' => $checkout->offer_version],
            'next_action' => ['code' => ['image_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=']],
        ]]]);
    }
    throw new LogicException('Unexpected synthetic provider request.');
});

$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
