<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;

chdir($argv[1]);
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$checks = [
    'production' => app()->environment('production') && ! config('app.debug'),
    'canonical HTTPS URL' => config('app.url') === 'https://app.adzbyte.com',
    'protected environment' => ! is_link('.env') && (fileperms('.env') & 0777) === 0600,
    'protected compiled configuration' => is_file('bootstrap/cache/config.php') && ! is_link('bootstrap/cache/config.php') && (fileperms('bootstrap/cache/config.php') & 0777) === 0600,
    'encrypted secure sessions' => config('session.encrypt') && config('session.secure') && config('session.http_only') && config('session.same_site') === 'lax',
    'database cache and queue' => config('cache.default') === 'database' && config('queue.default') === 'database',
    'transactional queue' => config('queue.connections.database.after_commit') && config('queue.connections.database.retry_after') > 90,
    'same database queue' => (config('queue.connections.database.connection') ?? config('database.default')) === config('database.default'),
];
$migrator = app(Migrator::class);
$checks['all migrations applied'] = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
foreach ($checks as $label => $passed) {
    if (! $passed) {
        throw new RuntimeException('Deployment invariant failed: '.$label);
    }
}
echo "Production configuration, migration state and environment permissions pass.\n";
