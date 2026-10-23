<?php

use App\Actions\Users\UpdateManagedUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Records\RecordVisibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

if (count($argv) !== 7) {
    throw new RuntimeException('Expected isolated record concurrency worker arguments.');
}
[$script, $database, $actorId, $targetId, $inputJson, $barrier, $expectedEnvironment] = $argv;
if ($expectedEnvironment !== 'testing' || getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Record worker requires the testing environment.');
}
if (realpath(dirname($barrier)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($barrier), 'catalog-concurrency-')) {
    throw new RuntimeException('Expected a disposable record concurrency barrier.');
}
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require dirname(__DIR__).'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Record worker test database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected an existing disposable record database.');
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
$command = json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR);
if (! in_array($command['mode'] ?? null, ['delete-product', 'delete-user', 'delete-role', 'demote', 'assign'], true)) {
    throw new RuntimeException('Unsupported isolated record command.');
}
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Record worker barrier timed out.');
    }
    usleep(1000);
}
try {
    $actor = User::findOrFail((int) $actorId);
    if (str_starts_with($command['mode'], 'delete-')) {
        $model = match ($command['mode']) {
            'delete-product' => Product::class,
            'delete-user' => User::class,
            'delete-role' => Role::class,
        };
        app(RecordVisibility::class)->delete($actor, $model::findOrFail((int) $targetId), 0);
    } else {
        $target = User::findOrFail((int) $targetId);
        app(UpdateManagedUser::class)->handle($actor, $target, $target->only(['name', 'email']), $command['roles']);
    }
    echo json_encode(['status' => 'passed'], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    echo json_encode(['status' => 'denied', 'code' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
} catch (AuthorizationException) {
    echo json_encode(['status' => 'denied', 'code' => 403], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'denied', 'code' => 422], JSON_THROW_ON_ERROR);
}
