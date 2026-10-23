<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\RecordDeletion;
use App\Models\RecordDeletionEvent;
use App\Models\Role;
use App\Models\User;
use App\Support\Records\RecordVisibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class RecordVisibilityConcurrencyTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
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

    public function test_four_independent_delete_retries_preserve_one_marker_one_event_and_original_record(): void
    {
        $actor = $this->superAdministrator();
        $product = Product::factory()->create(['name' => 'Synthetic concurrent visibility record']);
        $original = $product->fresh()->getAttributes();
        $results = $this->race(array_fill(0, 4, [$actor->id, $product->id, ['mode' => 'delete-product']]));
        $this->assertCount(4, array_filter($results, fn (array $result): bool => $result['status'] === 'passed'), json_encode($results));
        $this->assertSame($original, $product->fresh()->getAttributes());
        $this->assertSame(1, RecordDeletion::count());
        $this->assertSame(1, RecordDeletionEvent::count());
        $marker = RecordDeletion::sole();
        $this->assertSame('products', $marker->record_type);
        $this->assertSame((string) $product->id, (string) $marker->record_id);
        $this->assertSame(1, $marker->version);
        $this->assertNotNull($marker->deleted_at);
        $this->assertSame('deleted', RecordDeletionEvent::sole()->action);
    }

    #[DataProvider('raceRounds')]
    public function test_delete_and_demote_race_always_leaves_one_active_super_administrator(int $round): void
    {
        $a = $this->superAdministrator();
        $b = $this->superAdministrator();
        $results = $this->race([
            [$a->id, $b->id, ['mode' => 'delete-user']],
            [$b->id, $a->id, ['mode' => 'demote', 'roles' => [UserRole::Administrator->value]]],
        ]);
        $this->assertExactlyOneSuccess($results);
        $this->assertGreaterThanOrEqual(1, RecordVisibility::activeSuperAdministratorCount());
        $deleteWon = $results[0]['status'] === 'passed';
        $this->assertSame($deleteWon, RecordVisibility::isDeleted($b->fresh()));
        $this->assertSame($deleteWon, $a->fresh()->hasRole(UserRole::SuperAdmin->value));
        $this->assertSame($deleteWon ? 1 : 0, RecordDeletionEvent::count());
    }

    #[DataProvider('raceRounds')]
    public function test_role_delete_and_assignment_cannot_leave_a_deleted_role_assigned(int $round): void
    {
        $a = $this->superAdministrator();
        $b = $this->superAdministrator();
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        $role = Role::create(['name' => 'synthetic_concurrent_custom_role', 'guard_name' => 'web']);
        $results = $this->race([
            [$a->id, $role->id, ['mode' => 'delete-role']],
            [$b->id, $customer->id, ['mode' => 'assign', 'roles' => [UserRole::Customer->value, $role->name]]],
        ]);
        $this->assertExactlyOneSuccess($results);
        $deleted = RecordVisibility::isDeleted($role->fresh());
        $assigned = $customer->fresh()->hasRole($role->name);
        $this->assertFalse($deleted && $assigned);
        $this->assertSame($results[0]['status'] === 'passed', $deleted);
        $this->assertSame($results[1]['status'] === 'passed', $assigned);
        $this->assertTrue($customer->fresh()->hasRole(UserRole::Customer->value));
        $this->assertSame($deleted ? 0 : 1, DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->count());
    }

    public static function raceRounds(): array
    {
        return [[1], [2]];
    }

    private function superAdministrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function assertExactlyOneSuccess(array $results): void
    {
        $this->assertCount(1, array_filter($results, fn (array $result): bool => $result['status'] === 'passed'), json_encode($results));
        $denied = array_values(array_filter($results, fn (array $result): bool => $result['status'] === 'denied'));
        $this->assertCount(1, $denied, json_encode($results));
        $this->assertContains($denied[0]['code'], [403, 404, 422]);
    }

    private function race(array $commands): array
    {
        $barrier = $this->temporary.'.records-go';
        foreach ($commands as [$actorId, $targetId, $input]) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/Records/command.php'), $this->database,
                (string) $actorId, (string) $targetId, json_encode($input, JSON_THROW_ON_ERROR), $barrier, 'testing'], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(25);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < count($commands) && microtime(true) < $deadline) {
            foreach ($this->workers as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }
            usleep(1000);
        }
        $this->assertCount(count($commands), glob($barrier.'.*.ready'));
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
