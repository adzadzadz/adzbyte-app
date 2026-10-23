<?php

namespace Tests\Feature;

use App\Actions\Catalog\CategoryActions;
use App\Enums\UserRole;
use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Pages\ViewProductCategory;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\ProductCategories\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Filament\CategoryInput;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_category_pages_reject_customers_and_administrators_without_capabilities(): void
    {
        $category = ProductCategory::create(['name' => 'Synthetic']);
        foreach ([UserRole::Customer, UserRole::Administrator] as $role) {
            $user = $this->actor($role);
            if ($role === UserRole::Customer) {
                $user->givePermissionTo(['ViewAny:ProductCategory', 'View:ProductCategory', 'Create:ProductCategory', 'Update:ProductCategory']);
            }
            $this->actingAs($user);
            foreach (['/admin/product-categories', '/admin/product-categories/create', '/admin/product-categories/'.$category->id, '/admin/product-categories/'.$category->id.'/edit'] as $path) {
                $this->get($path)->assertForbidden();
            }
        }
    }

    public function test_administrator_creates_and_toggles_a_category_with_read_only_history(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        Livewire::test(ListProductCategories::class)->assertCountTableRecords(0);
        Livewire::test(CreateProductCategory::class)->fillForm(['name' => 'Synthetic category', 'limit_one_per_cart' => true])->call('create')->assertHasNoFormErrors();
        $category = ProductCategory::firstOrFail();
        $this->assertTrue($category->limit_one_per_cart);
        Livewire::test(EditProductCategory::class, ['record' => $category->id])->fillForm(['name' => 'Updated', 'limit_one_per_cart' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($category->fresh()->limit_one_per_cart);
        $this->assertDatabaseCount('product_category_changes', 2);
        Livewire::test(ViewProductCategory::class, ['record' => $category->id])->assertSee('Updated');
        Livewire::test(ChangesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => ViewProductCategory::class])->assertCountTableRecords(2);
        $this->assertNotContains('delete', array_keys(ProductCategoryResource::getPages()));
    }

    public function test_product_forms_assign_and_clear_category_through_shared_actions(): void
    {
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        Livewire::test(CreateProduct::class)->fillForm(['name' => 'Synthetic categorized', 'category_id' => (string) $category->id])->call('create')->assertHasNoFormErrors();
        $product = Product::firstOrFail();
        $this->assertSame($category->id, $product->category_id);
        $this->assertDatabaseCount('product_changes', 1);
        Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['category_id' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->category_id);
        Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['category_id' => 999999])->call('save')->assertHasFormErrors(['category_id']);
    }

    public function test_read_only_staff_cannot_change_rules_and_history_reauthorizes(): void
    {
        $category = ProductCategory::create(['name' => 'Synthetic']);
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:ProductCategory', 'View:ProductCategory']);
        $this->actingAs($actor);
        Livewire::test(ListProductCategories::class)->assertCountTableRecords(1);
        Livewire::test(EditProductCategory::class, ['record' => $category->id])->assertForbidden();
        Livewire::test(CreateProductCategory::class)->assertForbidden();
        $manager = Livewire::test(ChangesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => ViewProductCategory::class]);
        $actor->revokePermissionTo('View:ProductCategory');
        $manager->call('$refresh')->assertForbidden();
        Livewire::test(ChangesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => ViewProductCategory::class])->assertForbidden();
    }

    public function test_lost_create_and_edit_responses_replay_once_and_uuid_conflicts_are_visible(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $this->actingAs($actor);
        $create = Livewire::test(CreateProductCategory::class)->fillForm(['name' => 'Retried category', 'limit_one_per_cart' => true]);
        $category = app(CategoryActions::class)->create($actor, CategoryInput::content($create->get('data')), $create->get('operationId'));
        $create->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('product_categories', 1);
        $this->assertDatabaseCount('product_category_changes', 1);
        $edit = Livewire::test(EditProductCategory::class, ['record' => $category->id])->fillForm(['name' => 'Retried update', 'limit_one_per_cart' => false]);
        app(CategoryActions::class)->update($actor, $category, CategoryInput::content($edit->get('data')), $edit->get('operationId'));
        $edit->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('product_category_changes', 2);
        $conflict = Livewire::test(CreateProductCategory::class)->fillForm(['name' => 'Original request', 'limit_one_per_cart' => false]);
        app(CategoryActions::class)->create($actor, CategoryInput::content($conflict->get('data')), $conflict->get('operationId'));
        $conflict->fillForm(['name' => 'Conflicting category'])->call('create')->assertHasFormErrors(['name']);
        $this->assertDatabaseCount('product_categories', 2);
        $this->assertDatabaseCount('product_category_changes', 3);
    }

    public function test_required_name_and_protected_input_cannot_mutate_category_identity(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        Livewire::test(CreateProductCategory::class)->fillForm(['name' => ''])->call('create')->assertHasFormErrors(['name']);
        Livewire::test(CreateProductCategory::class)->fillForm(['name' => 'Safe', 'limit_one_per_cart' => false])->set('data.public_id', 'injected')->call('create')->assertHasNoFormErrors();
        $this->assertNotSame('injected', ProductCategory::firstOrFail()->public_id);
        $this->assertSame(['viewAny', 'view', 'create', 'update'], config('filament-shield.resources.manage.'.ProductCategoryResource::class));
        $this->assertDatabaseCount('role_has_permissions', 0);
    }

    private function actor(UserRole $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($role->value);

        return $actor;
    }
}
