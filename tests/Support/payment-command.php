<?php

use App\Actions\Payments\PaymentAttemptActions;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

[$script, $database, $actorId, $reference, $operation, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Payment worker database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable payment database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
if (! str_starts_with($barrier, $database === getenv('TEST_MYSQL_DATABASE') ? sys_get_temp_dir().'/catalog-concurrency-' : $database)) {
    throw new RuntimeException('Expected a disposable barrier path.');
}
foreach (['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only', 'services.paymongo.methods' => ['qrph']]);
Http::preventStrayRequests();
Http::fake(function ($request) use ($barrier) {
    if (str_ends_with($request->url(), '/capabilities/payment_methods')) {
        return Http::response(['qrph']);
    }
    if ($request->method() === 'POST') {
        file_put_contents($barrier.'.posts', "create\n", FILE_APPEND | LOCK_EX);
        usleep(100000);

        return Http::response(['data' => ['id' => 'cs_concurrent', 'type' => 'checkout_session']]);
    }
    $attempt = PaymentAttempt::sole();

    return Http::response(['data' => ['id' => 'cs_concurrent', 'type' => 'checkout_session', 'attributes' => [
        ...$attempt->provider_payload['data']['attributes'], 'livemode' => false, 'status' => 'active', 'payments' => [], 'checkout_url' => 'https://checkout.paymongo.com/cs_concurrent#synthetic',
    ]]]);
});
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Payment barrier timed out.');
    }
    usleep(1000);
}
try {
    $attempt = app(PaymentAttemptActions::class)->start(User::findOrFail((int) $actorId), $reference, 2, $operation);
    echo json_encode(['status' => 'passed', 'reference' => $attempt->public_id], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'denied', 'code' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
