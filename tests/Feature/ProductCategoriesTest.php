<?php

namespace Tests\Feature;

use App\Actions\Catalog\CatalogActions;
use App\Actions\Catalog\CategoryActions;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCategoryChange;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_default_to_regular_and_preserve_immutable_identity(): void
    {
        $category = ProductCategory::create(['name' => 'Synthetic regular category']);
        $this->assertTrue(Str::isUlid($category->public_id));
        $this->assertFalse($category->limit_one_per_cart);
        $reference = $category->public_id;
        $category->update(['name' => 'Renamed category', 'limit_one_per_cart' => true]);
        $this->assertSame($reference, $category->public_id);
        $this->assertTrue($category->limit_one_per_cart);
        $category->public_id = (string) Str::ulid();
        $this->expectException(ValidationException::class);
        $category->save();
    }

    public function test_category_deletion_and_empty_names_are_rejected(): void
    {
        foreach (['', '   '] as $name) {
            try {
                ProductCategory::create(['name' => $name]);
                $this->fail('Category name must be meaningful.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('product_categories', 0);
            }
        }
        $category = ProductCategory::create(['name' => 'Synthetic']);
        $this->expectException(ValidationException::class);
        $category->delete();
    }

    public function test_category_services_authorize_and_audit_retry_safe_commands(): void
    {
        $actor = $this->administrator(['Create:ProductCategory', 'Update:ProductCategory']);
        $actions = app(CategoryActions::class);
        $operation = (string) Str::uuid();
        $category = $actions->create($actor, ['name' => 'Synthetic exclusive', 'limit_one_per_cart' => true], $operation);
        $this->assertTrue($category->is($actions->create($actor, ['name' => 'Synthetic exclusive', 'limit_one_per_cart' => true], $operation)));
        $this->assertDatabaseCount('product_categories', 1);
        $this->assertDatabaseCount('product_category_changes', 1);
        $operation = (string) Str::uuid();
        $actions->update($actor, $category, ['limit_one_per_cart' => false], $operation);
        $actions->update($actor, $category, ['limit_one_per_cart' => false], $operation);
        $this->assertDatabaseCount('product_category_changes', 2);
        $history = ProductCategoryChange::latest('id')->firstOrFail();
        $this->assertSame($actor->id, $history->actor_id);
        $this->assertTrue($history->before['limit_one_per_cart']);
        $this->assertFalse($history->after['limit_one_per_cart']);
        $this->expectException(ValidationException::class);
        $actions->update($actor, $category, ['limit_one_per_cart' => true], $operation);
    }

    public function test_customers_unprivileged_staff_and_revoked_actors_cannot_mutate_categories(): void
    {
        $category = ProductCategory::create(['name' => 'Synthetic']);
        $this->seed(RoleSeeder::class);
        foreach ([UserRole::Customer, UserRole::Administrator] as $role) {
            $actor = User::factory()->create();
            $actor->assignRole($role->value);
            if ($role === UserRole::Customer) {
                $actor->givePermissionTo(['Create:ProductCategory', 'Update:ProductCategory']);
            }
            foreach ([fn () => app(CategoryActions::class)->create($actor, ['name' => 'Forbidden'], (string) Str::uuid()), fn () => app(CategoryActions::class)->update($actor, $category, ['limit_one_per_cart' => true], (string) Str::uuid())] as $command) {
                try {
                    $command();
                    $this->fail('Category capability boundary required.');
                } catch (AuthorizationException) {
                    $this->assertFalse($category->fresh()->limit_one_per_cart);
                }
            }
        }
        $actor = $this->administrator(['Update:ProductCategory']);
        $operation = (string) Str::uuid();
        app(CategoryActions::class)->update($actor, $category, ['name' => 'Allowed'], $operation);
        $actor->revokePermissionTo('Update:ProductCategory');
        $this->expectException(AuthorizationException::class);
        app(CategoryActions::class)->update($actor, $category, ['name' => 'Allowed'], $operation);
    }

    public function test_audit_is_immutable_and_command_keys_cannot_change_identity(): void
    {
        $actor = $this->administrator(['Create:ProductCategory']);
        try {
            app(CategoryActions::class)->create($actor, ['name' => 'Invalid', 'public_id' => (string) Str::ulid()], (string) Str::uuid());
            $this->fail('Protected category fields must not be accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_category_changes', 0);
        }
        app(CategoryActions::class)->create($actor, ['name' => 'Valid'], (string) Str::uuid());
        $history = ProductCategoryChange::firstOrFail();
        $this->expectException(ValidationException::class);
        $history->delete();
    }

    public function test_product_assignment_uses_the_catalog_service_and_cannot_edit_the_category_rule(): void
    {
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $actor = $this->administrator(['Create:Product', 'Update:Product']);
        $product = app(CatalogActions::class)->create($actor, ['name' => 'Synthetic', 'category_id' => $category->id], (string) Str::uuid());
        $this->assertTrue($product->category->is($category));
        $this->assertFalse(Gate::forUser($actor)->allows('update', $category));
        app(CatalogActions::class)->update($actor, $product, ['category_id' => null], (string) Str::uuid());
        $this->assertNull($product->fresh()->category_id);
        $this->assertTrue($category->fresh()->limit_one_per_cart);
    }

    public function test_public_category_projection_is_opt_in_safe_and_revalidated(): void
    {
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $product = Product::factory()->published()->create(['category_id' => $category->id]);
        Product::factory()->published()->create();
        Product::factory()->create(['category_id' => $category->id]);
        $uri = '/api/v1/products/'.$product->public_id;
        $legacy = $this->getJson($uri)->assertOk();
        $this->assertArrayNotHasKey('category', $legacy->json('data'));
        $this->assertArrayNotHasKey('category_id', $legacy->json('data'));
        $this->getJson('/api/v1/products?category='.$category->public_id)->assertOk()->assertJsonCount(1, 'data');
        $response = $this->getJson($uri.'?include=category')->assertOk()->assertJsonPath('data.category', [
            'public_id' => $category->public_id, 'name' => 'Restricted', 'limit_one_per_cart' => true,
        ]);
        $tag = $response->headers->get('ETag');
        $category->update(['name' => 'Updated', 'limit_one_per_cart' => false]);
        $this->getJson($uri.'?include=category', ['If-None-Match' => $tag])->assertOk()
            ->assertJsonPath('data.category.name', 'Updated')->assertJsonPath('data.category.limit_one_per_cart', false);
        $this->getJson($uri, ['If-None-Match' => $legacy->headers->get('ETag')])->assertStatus(304);
        $this->getJson('/api/v1/products?include=category')->assertOk()->assertJsonPath('data.1.category', null);
        $this->getJson('/api/v1/products?category=1')->assertUnprocessable();
        $this->getJson($uri.'?include=changes')->assertUnprocessable();
    }

    private function administrator(array $permissions): User
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Administrator->value);
        $actor->givePermissionTo($permissions);

        return $actor;
    }
}
