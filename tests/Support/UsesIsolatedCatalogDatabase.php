<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait UsesIsolatedCatalogDatabase
{
    private string $database;

    private string $temporary;

    private string $originalConnection;

    private bool $mysql = false;

    private bool $databaseCreated = false;

    private function setUpCatalogDatabase(bool $migrate = true): void
    {
        $this->temporary = tempnam(sys_get_temp_dir(), 'catalog-concurrency-');
        $this->originalConnection = DB::getDefaultConnection();
        $this->mysql = DB::connection()->getDriverName() === 'mysql';
        if ($this->mysql) {
            $this->database = 'adzbyte_test_catalog_'.Str::lower(Str::random(12));
            DB::statement('CREATE DATABASE `'.$this->database.'`');
            $this->databaseCreated = true;
            $connection = [...DB::connection()->getConfig(), 'database' => $this->database, 'url' => null];
        } else {
            $this->database = $this->temporary;
            $connection = [
                'driver' => 'sqlite', 'database' => $this->database,
                'foreign_key_constraints' => true, 'busy_timeout' => 5000,
            ];
        }
        config([
            'database.default' => 'catalog_concurrency',
            // Laravel's migrator resolves the connection by its stored name.
            // Never inherit the original connection's name with its credentials.
            'database.connections.catalog_concurrency' => [...$connection, 'name' => 'catalog_concurrency'],
        ]);
        $this->assertSame('catalog_concurrency', DB::connection()->getName());
        $this->assertSame($this->database, DB::connection()->getDatabaseName());
        if ($migrate) {
            $this->artisan('migrate', ['--database' => 'catalog_concurrency', '--force' => true])->assertSuccessful();
        }
    }

    private function tearDownCatalogDatabase(): void
    {
        DB::purge('catalog_concurrency');
        if ($this->databaseCreated) {
            DB::connection($this->originalConnection)->statement('DROP DATABASE `'.$this->database.'`');
        }
        foreach (isset($this->temporary) ? glob($this->temporary.'*') : [] as $path) {
            unlink($path);
        }
    }

    private function catalogWorkerEnvironment(): array
    {
        $environment = [
            'APP_ENV' => 'testing',
            'APP_CONFIG_CACHE' => $this->temporary.'.unused-config',
            'CATALOG_TEST_DRIVER' => $this->mysql ? 'mysql' : 'sqlite',
        ];
        if ($this->mysql) {
            $connection = DB::connection()->getConfig();
            $environment += [
                'TEST_MYSQL_DATABASE' => $this->database,
                'TEST_MYSQL_HOST' => $connection['host'],
                'TEST_MYSQL_PORT' => (string) $connection['port'],
                'TEST_MYSQL_USERNAME' => $connection['username'],
                'TEST_MYSQL_PASSWORD' => $connection['password'],
                'TEST_MYSQL_SOCKET' => $connection['unix_socket'] ?? '',
            ];
        }

        return $environment;
    }
}
