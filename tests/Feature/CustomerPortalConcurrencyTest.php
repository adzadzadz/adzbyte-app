<?php

namespace Tests\Feature;

use App\Enums\PortalCapability;
use App\Models\User;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\Portal\CreatesPortal;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CustomerPortalConcurrencyTest extends TestCase
{
    use CreatesPortal, UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->setUpPortal();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    #[DataProvider('modes')]
    public function test_independent_process_retries_and_staff_contention(string $mode): void
    {
        $work = $this->portalWork();
        $secondAdmin = User::factory()->create();
        $secondAdmin->assignRole('administrator');
        $secondAdmin->givePermissionTo(array_column(PortalCapability::cases(), 'value'));
        $key = (string) Str::uuid();
        $barrier = $this->temporary.'.portal-go';
        foreach (range(1, 4) as $i) {
            $input = $mode === 'create' ? ['mode' => 'create', 'input' => $this->caseInput()]
                : ['mode' => 'publish', 'input' => ['expected_revision' => 1, 'body' => 'Concurrent update']];
            $actor = $mode === 'create' ? $this->portalCustomer : ($i % 2 ? $this->portalAdmin : $secondAdmin);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/Portal/command.php'), $this->database, (string) $actor->id,
                $work->public_id, json_encode($input, JSON_THROW_ON_ERROR), $mode === 'create' ? $key : (string) Str::uuid(), $barrier], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(25);
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
        $this->assertCount(4, glob($barrier.'.*.ready'));
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertCount($mode === 'create' ? 4 : 1, array_filter($results, fn ($r) => $r['status'] === 'passed'), json_encode($results));
        $this->assertCount($mode === 'create' ? 0 : 3, array_filter($results, fn ($r) => $r['status'] === 'denied' && $r['code'] === 409), json_encode($results));
        $this->assertDatabaseCount('support_cases', $mode === 'create' ? 1 : 0);
        $this->assertDatabaseCount('work_updates', $mode === 'create' ? 0 : 1);
        $this->assertDatabaseCount('portal_commands', 2);
        $this->assertDatabaseCount('portal_events', 2);
    }

    public static function modes(): array
    {
        return [['create'], ['publish']];
    }
}
