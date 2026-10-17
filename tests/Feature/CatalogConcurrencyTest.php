<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CatalogConcurrencyTest extends TestCase
{
    private string $database;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = tempnam(sys_get_temp_dir(), 'catalog-concurrency-');
        config([
            'database.default' => 'catalog_concurrency',
            'database.connections.catalog_concurrency' => [
                'driver' => 'sqlite', 'database' => $this->database,
                'foreign_key_constraints' => true, 'busy_timeout' => 5000,
            ],
        ]);
        $this->artisan('migrate', ['--database' => 'catalog_concurrency', '--force' => true])->assertSuccessful();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::purge('catalog_concurrency');
        foreach (glob($this->database.'*') as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    #[DataProvider('revisionCommands')]
    public function test_independent_processes_allocate_unique_contiguous_revisions(string $command, string $table): void
    {
        $results = $this->race($command, false);
        $this->assertCount(4, array_unique(array_column($results, 'id')));
        $this->assertSame([1, 2, 3, 4], DB::table($table)->orderBy('version')->pluck('version')->all());
        $this->assertDatabaseCount('product_changes', 4);
    }

    public static function revisionCommands(): array
    {
        return [['price', 'product_prices'], ['terms', 'product_terms']];
    }

    #[DataProvider('retryCommands')]
    public function test_concurrent_identical_commands_commit_only_once(string $command, string $table): void
    {
        $results = $this->race($command, true);
        $this->assertCount(1, array_unique(array_column($results, 'id')));
        $this->assertDatabaseCount($table, 1);
        $this->assertDatabaseCount('product_changes', 1);
    }

    public static function retryCommands(): array
    {
        return [['create', 'products'], ['price', 'product_prices'], ['terms', 'product_terms']];
    }

    private function race(string $command, bool $sameOperation): array
    {
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::SuperAdmin->value);
        $product = $command === 'create' ? null : Product::factory()->create();
        $operationId = (string) Str::uuid();
        $barrier = $this->database.'.go';
        for ($i = 0; $i < 4; $i++) {
            $worker = new Process([
                PHP_BINARY, base_path('tests/Support/catalog-command.php'),
                $this->database, (string) $actor->id, (string) ($product?->id ?? 0),
                $command, $sameOperation ? $operationId : (string) Str::uuid(), $barrier,
            ], base_path(), ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $this->database.'.unused-config']);
            $worker->setTimeout(20);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 4 && microtime(true) < $deadline) {
            foreach ($this->workers as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }
            usleep(1000);
        }
        $this->assertCount(4, glob($barrier.'.*.ready'), 'All workers must reach the common start barrier.');
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $this->assertTrue(json_validate($worker->getOutput()), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
