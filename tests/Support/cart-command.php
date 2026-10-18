<?php

use App\Actions\Carts\CartActions;
use App\Services\Carts\CartPrincipal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Standalone race worker. The parent creates and removes the guarded test DB.
[$script, $database, $secret, $command, $cartReference, $productReference, $operationId, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Worker test database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable cart database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
foreach (['CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$principal = CartPrincipal::guest($secret);
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Cart test barrier timed out.');
    }
    usleep(1000);
}
try {
    $actions = app(CartActions::class);
    $result = match ($command) {
        'create' => $actions->create($principal, $operationId),
        'add' => $actions->add($principal, $cartReference, $productReference, $operationId),
    };
    echo json_encode(['status' => 'passed', 'reference' => $result['public_id'], 'lines' => count($result['lines'])], JSON_THROW_ON_ERROR);
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR);
}
