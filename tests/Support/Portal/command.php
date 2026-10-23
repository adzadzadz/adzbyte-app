<?php

use App\Actions\Portal\PortalActions;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

[$script, $database, $actorId, $reference, $inputJson, $operationId, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require dirname(__DIR__).'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Order worker test database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable order database.');
    }
    require dirname(__DIR__, 3).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
foreach (['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
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
    $command = json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR);
    $actions = app(PortalActions::class);
    $actor = User::findOrFail((int) $actorId);
    $result = match ($command['mode']) {
        'create' => $actions->createCase($actor, $command['input'], $operationId),
        'publish' => $actions->publishUpdate($actor, $reference, $command['input'], $operationId),
    };
    echo json_encode(['status' => 'passed', 'reference' => $result->public_id], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'denied', 'code' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'denied', 'code' => 422], JSON_THROW_ON_ERROR);
}
