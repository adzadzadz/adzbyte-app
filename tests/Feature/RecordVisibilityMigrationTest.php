<?php

namespace Tests\Feature;

use App\Models\RecordDeletionEvent;
use App\Models\User;
use App\Models\WorkItem;
use App\Support\Records\RecordVisibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class RecordVisibilityMigrationTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase(migrate: false);
    }

    protected function tearDown(): void
    {
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_upgrade_empty_reverse_reapply_and_populated_history_guard(): void
    {
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_10_24_000000_create_record_deletions.php'));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertSuccessful();
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('super_admin');
        $work = WorkItem::create(['public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'type' => 'project', 'title' => 'Preserved prior work', 'summary' => 'Synthetic migration fixture']);
        $before = $work->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_24_000000_create_record_deletions.php');
        $migration->up();
        $this->assertTrue(Schema::hasTable('record_deletions'));
        $this->assertSame($before, $work->fresh()->getAttributes());
        $migration->down();
        $this->assertFalse(Schema::hasTable('record_deletions'));
        $this->assertTrue(Schema::hasTable('work_items'));
        $migration->up();
        app(RecordVisibility::class)->delete($owner, $work);
        try {
            $migration->down();
            $this->fail('Deletion history was removed');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Deletion history must be retained', $exception->getMessage());
        }
        $this->assertSame($before, $work->fresh()->getAttributes());
        $this->assertSame(1, RecordDeletionEvent::count());
    }
}
