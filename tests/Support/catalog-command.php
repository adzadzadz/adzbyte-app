<?php

// Standalone worker for independent-process catalog concurrency tests. It only
// accepts an existing disposable test database supplied by the parent test.
use App\Actions\Catalog\CatalogActions;
use App\Actions\Catalog\CategoryActions;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
} else {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $database, $actorId, $productId, $command, $operationId, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('The worker must use its isolated test database.');
    }
} elseif (realpath(dirname($database)) !== realpath(sys_get_temp_dir())
    || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
    throw new RuntimeException('Expected an existing disposable catalog test database.');
}
if (getenv('CATALOG_TEST_DRIVER') !== 'mysql') {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.busy_timeout' => 5000,
    ]);
}
config(['cache.default' => 'array']);
$actor = User::findOrFail($actorId);
$product = in_array($command, ['create', 'category_create'], true) ? null : Product::findOrFail($productId);
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent test barrier timed out.');
    }
    usleep(1000);
}
$actions = app(CatalogActions::class);
$result = match ($command) {
    'category_create' => app(CategoryActions::class)->create($actor, ['name' => 'Synthetic concurrent category', 'limit_one_per_cart' => true], $operationId),
    'create' => $actions->create($actor, ['name' => 'Synthetic concurrent draft'], $operationId),
    'price' => $actions->revisePrice($actor, $product, ['amount_minor' => 12500, 'currency' => 'PHP'], $operationId),
    'terms' => $actions->reviseTerms($actor, $product, ['scope' => 'Synthetic concurrent terms'], $operationId),
};
echo json_encode(['id' => $result->id, 'version' => $result->version], JSON_THROW_ON_ERROR);
