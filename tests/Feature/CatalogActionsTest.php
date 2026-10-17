<?php

namespace Tests\Feature;

use App\Actions\Catalog\CatalogActions;
use App\Enums\ProductCapability;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductChange;
use App\Models\User;
use App\Policies\ProductPolicy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CatalogActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_permissions_are_defined_without_granting_regular_roles_or_seeding_products(): void
    {
        $this->seed(RoleSeeder::class);
        foreach (ProductCapability::cases() as $capability) {
            $this->assertDatabaseHas('permissions', ['name' => $capability->value, 'guard_name' => 'web']);
        }
        $this->assertDatabaseCount('role_has_permissions', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertFalse(Gate::allows('create', Product::class));
    }

    #[DataProvider('commands')]
    public function test_every_mutation_rejects_customers_and_unprivileged_administrators(string $method, string $capability, array $payload): void
    {
        $product = Product::factory()->create();
        foreach ([UserRole::Customer, UserRole::Administrator] as $role) {
            $actor = $this->actor($role);
            if ($role === UserRole::Customer) {
                $actor->givePermissionTo($capability);
            }
            try {
                $this->invoke($method, $actor, $product, $payload);
                $this->fail('Catalog mutations must require staff capabilities.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('product_changes', 0);
            }
        }
        $this->assertSame(ProductStatus::Draft, $product->refresh()->status);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_prices', 0);
        $this->assertDatabaseCount('product_terms', 0);
    }

    public static function commands(): array
    {
        return [
            'create' => ['create', 'Create:Product', [[]]],
            'content' => ['update', 'Update:Product', [['name' => 'Edited']]],
            'publish' => ['publish', 'Publish:Product', []],
            'unpublish' => ['unpublish', 'Unpublish:Product', []],
            'availability' => ['setAvailability', 'SetAvailability:Product', [false]],
            'archive' => ['archive', 'Archive:Product', []],
            'restore' => ['restore', 'Restore:Product', []],
            'price' => ['revisePrice', 'RevisePrice:Product', [['amount_minor' => 12500, 'currency' => 'PHP']]],
            'terms' => ['reviseTerms', 'ReviseTerms:Product', [['scope' => 'Synthetic']]],
        ];
    }

    #[DataProvider('commands')]
    public function test_each_command_requires_its_own_capability(string $method, string $capability, array $payload): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(array_values(array_diff(
            array_column(ProductCapability::cases(), 'value'), [$capability],
        )));
        $this->expectException(AuthorizationException::class);
        $this->invoke($method, $actor, Product::factory()->create(), $payload);
    }

    public function test_narrow_capabilities_allow_content_but_not_publication_or_versions(): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product']);
        $actions = app(CatalogActions::class);
        $product = $actions->create($actor, [], (string) Str::uuid());
        $edited = $actions->update($actor, $product, ['name' => 'Synthetic', 'media' => ['image' => 'fixture.webp']], (string) Str::uuid());

        $this->assertSame($product->public_id, $edited->public_id);
        $this->assertSame('Synthetic', $edited->name);
        $this->assertSame(ProductStatus::Draft, $edited->status);
        $this->assertFalse($edited->is_available);
        $this->assertTrue(Gate::forUser($actor)->allows('view', $product));
        $this->assertFalse(Gate::forUser($actor)->allows('publish', $product));
        $this->assertFalse(Gate::forUser($actor)->allows('revisePrice', $product));
        $this->assertFalse((new ProductPolicy)->delete($actor, $product));
        $customer = $this->actor(UserRole::Customer);
        $customer->givePermissionTo(['View:Product', 'ViewAny:Product']);
        $this->assertFalse(Gate::forUser($customer)->allows('view', $product));
        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', Product::class));
    }

    public function test_lifecycle_actions_record_actor_time_and_disable_availability(): void
    {
        $this->travelTo(now()->startOfSecond());
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = $actions->create($actor, ['name' => 'Synthetic'], (string) Str::uuid());
        $actions->revisePrice($actor, $product, ['amount_minor' => 15000, 'currency' => 'PHP'], (string) Str::uuid());
        $actions->reviseTerms($actor, $product, ['scope' => 'Synthetic scope'], (string) Str::uuid());
        $actions->publish($actor, $product, (string) Str::uuid());
        $this->assertFalse($product->refresh()->is_available);
        $actions->setAvailability($actor, $product, true, (string) Str::uuid());
        $this->assertTrue($product->refresh()->hasConfiguredOffer());
        $actions->unpublish($actor, $product, (string) Str::uuid());
        $this->assertFalse($product->refresh()->is_available);
        $actions->publish($actor, $product, (string) Str::uuid());
        $actions->setAvailability($actor, $product, true, (string) Str::uuid());
        $actions->archive($actor, $product, (string) Str::uuid());
        $this->assertSame(ProductStatus::Archived, $product->refresh()->status);
        $this->assertFalse($product->is_available);
        $actions->restore($actor, $product, (string) Str::uuid());
        $this->assertSame(ProductStatus::Draft, $product->refresh()->status);
        $this->assertFalse($product->is_available);
        $this->assertDatabaseCount('product_changes', 10);
        $audit = ProductChange::query()->where('action', 'archive')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertTrue($audit->created_at->equalTo(now()));
        $this->assertSame('published', $audit->before['status']);
        $this->assertTrue($audit->before['is_available']);
        $this->assertSame('archived', $audit->after['status']);
        $this->assertFalse($audit->after['is_available']);
    }

    public function test_model_guards_are_applied_to_fresh_records_inside_the_service(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = Product::factory()->create();
        $actions->archive($actor, $product, (string) Str::uuid());
        // The caller still holds the earlier draft instance.
        $this->assertSame(ProductStatus::Draft, $product->status);
        foreach (['publish', 'unpublish'] as $method) {
            try {
                $actions->$method($actor, $product, (string) Str::uuid());
                $this->fail('An archive must be restored first.');
            } catch (ValidationException) {
                $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
            }
        }
        $this->assertDatabaseCount('product_changes', 1);
    }

    public function test_incomplete_products_cannot_be_published_or_enabled_and_failures_leave_no_receipts(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $blank = Product::create([]);
        $published = Product::factory()->published()->create();
        foreach ([
            fn () => $actions->publish($actor, $blank, (string) Str::uuid()),
            fn () => $actions->setAvailability($actor, $blank, true, (string) Str::uuid()),
            fn () => $actions->setAvailability($actor, $published, true, (string) Str::uuid()),
            fn () => $actions->restore($actor, $published, (string) Str::uuid()),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Incomplete or invalid transitions must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('product_changes', 0);
            }
        }
        $this->assertSame(ProductStatus::Draft, $blank->fresh()->status);
        $this->assertFalse($published->fresh()->is_available);
    }

    public function test_replaying_an_old_command_does_not_undo_newer_content_or_prices(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $creation = (string) Str::uuid();
        $product = $actions->create($actor, [], $creation);
        $priceOperation = (string) Str::uuid();
        $originalPrice = $actions->revisePrice($actor, $product, ['amount_minor' => 100, 'currency' => 'PHP'], $priceOperation);
        $actions->update($actor, $product, ['name' => 'Newer content'], (string) Str::uuid());
        $newPrice = $actions->revisePrice($actor, $product, ['amount_minor' => 200, 'currency' => 'PHP'], (string) Str::uuid());

        $this->assertSame('Newer content', $actions->create($actor, [], strtoupper($creation))->name);
        $this->assertTrue($originalPrice->is($actions->revisePrice($actor, $product, ['amount_minor' => 100, 'currency' => 'PHP'], $priceOperation)));
        $this->assertTrue($newPrice->is($product->currentPrice()->first()));
        $this->assertDatabaseCount('product_changes', 4);
    }

    public function test_new_revisions_preserve_history_and_disable_incomplete_offers(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = Product::factory()->published()->create();
        $price = $actions->revisePrice($actor, $product, ['amount_minor' => 15000, 'currency' => 'PHP'], (string) Str::uuid());
        $terms = $actions->reviseTerms($actor, $product, ['scope' => 'Original'], (string) Str::uuid());
        $actions->setAvailability($actor, $product, true, (string) Str::uuid());
        $zero = $actions->revisePrice($actor, $product, ['amount_minor' => 0, 'currency' => 'PHP'], (string) Str::uuid());
        $this->assertSame(2, $zero->version);
        $this->assertFalse($product->refresh()->is_available);
        $actions->revisePrice($actor, $product, ['amount_minor' => 20000, 'currency' => 'PHP'], (string) Str::uuid());
        $actions->setAvailability($actor, $product, true, (string) Str::uuid());
        $empty = $actions->reviseTerms($actor, $product, [], (string) Str::uuid());
        $this->assertSame(2, $empty->version);
        $this->assertFalse($product->refresh()->is_available);
        $this->assertSame(15000, $price->fresh()->amount_minor);
        $this->assertSame(['scope' => 'Original'], $terms->fresh()->content);
    }

    #[DataProvider('commands')]
    public function test_command_retries_replay_without_duplicate_writes(string $method, string $capability, array $payload): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo($capability);
        $product = Product::factory()->create();
        if ($method === 'restore') {
            $product->forceFill(['status' => ProductStatus::Archived])->save();
        }
        $id = (string) Str::uuid();
        $first = $this->invoke($method, $actor, $product, $payload, $id);
        $second = $this->invoke($method, $actor, $product, $payload, $id);
        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('product_changes', 1);
        $actor->revokePermissionTo($capability);
        $this->expectException(AuthorizationException::class);
        $this->invoke($method, $actor, $product, $payload, $id);
    }

    public function test_retry_payload_order_is_canonical_but_changed_payload_command_and_target_conflict(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = Product::factory()->create();
        $id = (string) Str::uuid();
        $actions->update($actor, $product, ['name' => 'Synthetic', 'media' => ['a' => 1, 'b' => 2]], $id);
        $actions->update($actor, $product, ['media' => ['b' => 2, 'a' => 1], 'name' => 'Synthetic'], $id);
        foreach ([
            fn () => $actions->update($actor, $product, ['name' => 'Changed'], $id),
            fn () => $actions->archive($actor, $product, $id),
            fn () => $actions->update($actor, Product::factory()->create(), ['name' => 'Synthetic'], $id),
        ] as $conflict) {
            try {
                $conflict();
                $this->fail('A retry key cannot be repurposed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('operation_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('product_changes', 1);
        $this->assertSame('Synthetic', $product->fresh()->name);
    }

    public function test_identical_retry_keys_are_scoped_to_the_actor(): void
    {
        $actions = app(CatalogActions::class);
        $id = (string) Str::uuid();
        $one = $actions->create($this->actor(UserRole::SuperAdmin), [], $id);
        $two = $actions->create($this->actor(UserRole::SuperAdmin), [], $id);
        $this->assertFalse($one->is($two));
        $this->assertDatabaseCount('product_changes', 2);
    }

    public function test_invalid_data_or_audit_failure_rolls_back_and_the_same_key_can_be_retried(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = Product::factory()->create();
        $id = (string) Str::uuid();
        foreach ([['amount_minor' => 1.5, 'currency' => 'PHP'], ['amount_minor' => 100, 'currency' => 'USD']] as $invalid) {
            try {
                $actions->revisePrice($actor, $product, $invalid, $id);
                $this->fail('Invalid versions must roll back.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('product_prices', 0);
                $this->assertDatabaseCount('product_changes', 0);
            }
        }
        $actions->revisePrice($actor, $product, ['amount_minor' => 100, 'currency' => 'PHP'], $id);
        $listener = static function (): never {
            throw new RuntimeException('Synthetic audit failure');
        };
        ProductChange::creating($listener);
        try {
            $actions->update($actor, $product, ['name' => 'Must roll back'], (string) Str::uuid());
            $this->fail('A mutation cannot commit without its audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
            $this->assertNotSame('Must roll back', $product->fresh()->name);
            $this->assertDatabaseCount('product_changes', 1);
        } finally {
            ProductChange::flushEventListeners();
            ProductChange::clearBootedModels();
        }
    }

    public function test_content_cannot_inject_lifecycle_identity_or_revision_fields(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $actions = app(CatalogActions::class);
        $product = Product::factory()->create();
        foreach (['status' => 'published', 'is_available' => true, 'public_id' => (string) Str::ulid(), 'id' => 12, 'version' => 3] as $field => $value) {
            foreach (['create', 'update'] as $method) {
                try {
                    $this->invoke($method, $actor, $product, [[$field => $value]]);
                    $this->fail('Unknown content fields must be rejected.');
                } catch (ValidationException) {
                    $this->assertDatabaseCount('product_changes', 0);
                }
            }
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_audit_history_is_append_only_and_has_database_uniqueness_and_foreign_keys(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $product = app(CatalogActions::class)->create($actor, [], (string) Str::uuid());
        $audit = ProductChange::query()->sole();
        foreach (['update', 'delete'] as $method) {
            try {
                $method === 'update' ? $audit->forceFill(['action' => 'changed'])->save() : $audit->delete();
                $this->fail('Audit history is append-only.');
            } catch (ValidationException) {
                $this->assertSame('create', $audit->fresh()->action);
            }
        }
        foreach ([
            fn () => DB::table('product_changes')->insert(collect($audit->getRawOriginal())->except('id')->all()),
            fn () => DB::table('products')->where('id', $product->id)->delete(),
            fn () => DB::table('users')->where('id', $actor->id)->delete(),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Database constraints must protect receipts.');
            } catch (QueryException) {
                $this->assertDatabaseCount('product_changes', 1);
            }
        }
    }

    public function test_invalid_operation_ids_do_not_write(): void
    {
        $this->expectException(ValidationException::class);
        app(CatalogActions::class)->create($this->actor(UserRole::SuperAdmin), [], 'invalid');
    }

    private function actor(UserRole $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($role->value);

        return $actor;
    }

    private function invoke(string $method, User $actor, Product $product, array $payload, ?string $id = null): mixed
    {
        return app(CatalogActions::class)->$method(...[
            $actor, ...($method === 'create' ? [] : [$product]), ...$payload, $id ?? (string) Str::uuid(),
        ]);
    }
}
