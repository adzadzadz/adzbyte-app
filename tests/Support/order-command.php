<?php

use App\Actions\Orders\OrderActions;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

[$script, $database, $actorId, $inputJson, $operationId, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Order worker test database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable order database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
foreach (['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Order worker barrier timed out.');
    }
    usleep(1000);
}
try {
    if ($inputJson === 'review') {
        $review = app(OrderActions::class)->review(User::findOrFail((int) $actorId));
        echo json_encode(['status' => 'passed', 'policy' => $review['policy']->public_id], JSON_THROW_ON_ERROR);
        exit;
    }
    $order = app(OrderActions::class)->submit(User::findOrFail((int) $actorId), json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR), $operationId);
    echo json_encode(['status' => 'passed', 'reference' => $order->public_id, 'lines' => $order->currentRevision->lines->count(), 'total' => $order->currentRevision->total_minor], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'denied', 'code' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'denied', 'code' => 422], JSON_THROW_ON_ERROR);
}
