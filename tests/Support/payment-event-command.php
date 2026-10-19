<?php

use App\Actions\Payments\PaymentControlActions;
use App\Actions\Payments\PaymentEventActions;
use App\Actions\Payments\PaymentOperations;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PaymentEventFixture;

[$script, $database, $body, $barrier] = $argv;
$paymentWorkerCommand = $argv[4] ?? 'receive';
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
config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only', 'services.paymongo.webhook_secret' => PaymentEventFixture::SECRET]);
Http::preventStrayRequests();
if (! in_array($paymentWorkerCommand, ['receive', 'reconcile', 'control'], true)) {
    throw new RuntimeException('Unknown payment worker command.');
}
if ($paymentWorkerCommand === 'reconcile') {
    $input = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    Http::fake(fn () => Http::response($input['provider']));
}
if ($paymentWorkerCommand === 'control') {
    $input = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    foreach (['remote_file', 'post_log'] as $field) {
        if (realpath(dirname($input[$field])) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($input[$field]), 'catalog-concurrency-') || ! is_file($input[$field])) {
            throw new RuntimeException('Expected disposable control worker fixture.');
        }
    }
    Http::fake(function ($request) use ($input) {
        if ($request->method() === 'POST') {
            if ($request->url() !== 'https://api.paymongo.com/v1/checkout_sessions/cs_signed/expire') {
                throw new RuntimeException('Unexpected control worker expiry endpoint.');
            }
            file_put_contents($input['post_log'], "expire\n", FILE_APPEND | LOCK_EX);
            file_put_contents($input['remote_file'], 'expired', LOCK_EX);
        }
        $provider = $input['provider'];
        $provider['data']['attributes']['status'] = trim(file_get_contents($input['remote_file']));

        return Http::response($provider);
    });
}
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Payment event barrier timed out.');
    }
    usleep(1000);
}
try {
    if ($paymentWorkerCommand === 'control') {
        $command = app(PaymentControlActions::class)->start(User::findOrFail($input['actor']), $input['order'], 'cancel',
            ['expected_revision' => 3, 'note' => 'Synthetic concurrent cancellation'], $input['operation']);
        echo json_encode(['status' => 'passed', 'control' => $command->public_id, 'state' => $command->state], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($paymentWorkerCommand === 'reconcile') {
        $resolution = app(PaymentOperations::class)->reconcile(User::findOrFail($input['actor']), $input['attempt'], $input['event'],
            ['expected_revision' => 3, 'note' => 'Synthetic concurrent reconciliation'], $input['operation']);
        echo json_encode(['status' => 'passed', 'resolution' => $resolution->public_id], JSON_THROW_ON_ERROR);
        exit;
    }
    $event = app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
    echo json_encode(['status' => 'passed', 'state' => $event->state], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'denied', 'code' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
