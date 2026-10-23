<?php

use App\Actions\Customers\RegisterPublicCustomer;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

// Standalone registration/queue test worker. Never boot a development target.
[$script, $database, $mode, $argument, $barrier] = $argv;
if (getenv('CATALOG_TEST_DRIVER') === 'mysql') {
    require __DIR__.'/mysql-bootstrap.php';
    if ($database !== getenv('TEST_MYSQL_DATABASE')) {
        throw new RuntimeException('Worker test database mismatch.');
    }
} else {
    if (realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! str_starts_with(basename($database), 'catalog-concurrency-') || ! is_file($database)) {
        throw new RuntimeException('Expected a disposable registration database.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
foreach (['CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => $mode === 'race' ? 'sync' : 'database'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Serialized models retain the isolated connection name from the parent process.
config(['database.connections.catalog_concurrency' => [...DB::connection()->getConfig(), 'name' => 'catalog_concurrency']]);
if ($mode === 'queue') {
    if (config('mail.default') !== 'smtp' || config('mail.mailers.smtp.host') !== '127.0.0.1' || config('mail.mailers.smtp.username') || config('mail.mailers.smtp.url') !== null) {
        throw new RuntimeException('Queue transport proof must use credential-free loopback SMTP.');
    }
    Event::listen(JobExceptionOccurred::class, function ($event): void {
        echo 'Test transport failure: '.$event->exception::class.' '.preg_replace('~https?://[^\s]+~', '[test-url]', $event->exception->getMessage())."\n";
    });
    Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--tries' => 1, '--sleep' => 0, '--timeout' => 15]);
    echo Artisan::output();
    echo json_encode(['remaining' => DB::table('jobs')->get(['queue', 'attempts', 'reserved_at', 'available_at'])->all(), 'users' => User::count(), 'now' => time()], JSON_THROW_ON_ERROR);
    exit;
}
if ($mode !== 'race') {
    throw new RuntimeException('Unknown test worker mode.');
}
Notification::fake();
touch($barrier.'.'.getmypid().'.ready');
$deadline = microtime(true) + 15;
while (! is_file($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Registration barrier timed out.');
    }
    usleep(1000);
}
app(RegisterPublicCustomer::class)->handle('Synthetic concurrent', $argument);
echo "Registration worker completed.\n";
