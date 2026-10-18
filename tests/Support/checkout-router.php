<?php

use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;

// Restricted loopback HTTP harness. Authentication uses real encrypted database
// sessions seeded by the parent; there is no test login or auth bypass route.
$database = getenv('CHECKOUT_HTTP_DATABASE');
if (getenv('APP_ENV') !== 'local' || ! is_string($database) || getenv('CHECKOUT_HTTP_ALLOW') !== '1') {
    throw new RuntimeException('Expected an explicitly enabled disposable checkout HTTP target.');
}
$driver = getenv('CATALOG_TEST_DRIVER') === 'mysql' ? 'mysql' : 'sqlite';
if ($driver === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Checkout HTTP database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable checkout database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
foreach (['APP_ENV' => 'local', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'checkout-contract-session',
    'SESSION_CONNECTION' => $driver, 'DB_CACHE_CONNECTION' => $driver, 'DB_CACHE_LOCK_CONNECTION' => $driver,
    'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$browser = getenv('CHECKOUT_HTTP_BROWSER') === '1';
if (! $browser && $path !== '/api/v1/checkout' && $path !== '/api/v1/orders' && $path !== '/api/v1/management/orders' && $path !== '/api/v1/management/commerce-settings' && ! preg_match('~^/api/v1/(?:management/)?orders/[0-9A-HJKMNP-TV-Z]{26}(?:/(?:payment|review|reply|resubmit|accept-proposal|approve|reject|clarify|message|note|propose))?$~D', $path)) {
    http_response_code(404);
    exit;
}
if ($browser) {
    $public = dirname(__DIR__, 2).'/public';
    $file = realpath($public.$path);
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp', 'woff2' => 'font/woff2', 'ico' => 'image/x-icon'];
    $extension = is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : '';
    if ($file !== false && str_starts_with($file, $public.'/') && is_file($file) && isset($types[$extension])) {
        header('Content-Type: '.$types[$extension]);
        readfile($file);
        exit;
    }
}
define('LARAVEL_START', microtime(true));
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->booted(function () use ($database): void {
    if (getenv('CHECKOUT_HTTP_BROWSER') === '1') {
        Vite::useHotFile($database.'.unused-hot');
    }
    if (DB::connection()->getDatabaseName() !== $database || app()->environment('testing')) {
        throw new RuntimeException('Checkout HTTP isolation or real CSRF guard mismatch.');
    }
    if (getenv('CHECKOUT_HTTP_PAYMENT_FAKE') === '1') {
        config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only', 'services.paymongo.methods' => ['qrph']]);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
                return Http::response(['qrph']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['id' => 'cs_http', 'type' => 'checkout_session']]);
            }
            $attempt = PaymentAttempt::sole();

            return Http::response(['data' => ['id' => 'cs_http', 'type' => 'checkout_session', 'attributes' => [
                ...$attempt->provider_payload['data']['attributes'], 'livemode' => false, 'status' => 'active', 'payments' => [], 'checkout_url' => 'https://checkout.paymongo.com/cs_http#synthetic',
            ]]]);
        });
    }
});
$app->handleRequest(Request::capture());
