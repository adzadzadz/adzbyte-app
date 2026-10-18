<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// A localhost HTTP harness, never a deployment entry point. It accepts only
// a parent-created disposable database and serves catalog routes. Restricted
// storefront routes/database cache require an explicit parent test opt-in.
$database = getenv('CATALOG_HTTP_DATABASE');
if (getenv('APP_ENV') !== 'testing' || ! is_string($database)) {
    throw new RuntimeException('Expected an explicit disposable catalog HTTP target.');
}
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('HTTP harness database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir())
        || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable catalog database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
$storefront = getenv('CATALOG_HTTP_ALLOW_STOREFRONT') === '1';
foreach (['CACHE_STORE' => $storefront ? 'database' : 'array', 'SESSION_DRIVER' => 'array'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (! str_starts_with($path, '/api/v1/products') && ! ($storefront && str_starts_with($path, '/api/v1/storefront/carts'))) {
    http_response_code(404);
    exit;
}
define('LARAVEL_START', microtime(true));
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->booted(function () use ($database, $storefront): void {
    if (getenv('CATALOG_TEST_DRIVER') !== 'mysql') {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $database,
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
    }
    config(['cache.default' => $storefront ? 'database' : 'array', 'session.driver' => 'array']);
});
$app->handleRequest(Request::capture());
