<?php

namespace Tests\Feature;

use App\Enums\PaymentCapability;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\PackageCheckouts\PackageCheckoutResource;
use App\Filament\Resources\PackageCheckouts\Pages\ListPackageCheckouts;
use App\Filament\Resources\PackageCheckouts\Pages\ViewPackageCheckout;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Pages\ViewProductCategory;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\PricesRelationManager;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\PackageCheckout;
use App\Models\PackagePurchaseDelivery;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\Role;
use App\Models\User;
use App\Support\Records\RecordVisibility;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRecordVisibilityResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole(UserRole::SuperAdmin->value);
        $this->administrator->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $this->actingAs($this->administrator);
    }

    public function test_all_six_resource_tables_offer_only_history_preserving_visibility_actions(): void
    {
        foreach ([ListProducts::class, ListProductCategories::class, ListUsers::class,
            ListOrders::class,
            ListPayments::class,
            ListPackageCheckouts::class] as $page) {
            $component = Livewire::test($page)->assertSuccessful();
            $table = $component->instance()->getTable();
            $this->assertArrayHasKey('record_visibility', $table->getFilters());
            $this->assertArrayHasKey('deleteRecord', $table->getFlatActions());
            $this->assertArrayHasKey('restoreRecord', $table->getFlatActions());
            $this->assertArrayNotHasKey('forceDelete', $table->getFlatActions());
            $this->assertArrayNotHasKey('delete', $table->getFlatActions());
        }
    }

    public function test_deleted_product_is_filtered_inspectable_and_cannot_be_edited_or_published(): void
    {
        $active = Product::factory()->create(['name' => 'Active service']);
        $deleted = Product::factory()->create(['name' => 'Deleted service']);
        app(RecordVisibility::class)->delete($this->administrator, $deleted);
        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$deleted]);
        Livewire::test(ListProducts::class)->filterTable('record_visibility', 'deleted')->assertCanSeeTableRecords([$deleted])->assertCanNotSeeTableRecords([$active]);
        Livewire::test(ViewProduct::class, ['record' => $deleted->id])->assertSee('Deleted — retained for history.')->assertActionDoesNotExist('publish');
        Livewire::test(EditProduct::class, ['record' => $deleted->id])->assertForbidden();
        app(RecordVisibility::class)->restore($this->administrator, $deleted);
        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$active, $deleted]);
        Livewire::test(EditProduct::class, ['record' => $deleted->id])->assertSuccessful();
        $this->assertDatabaseHas('products', ['id' => $deleted->id, 'name' => 'Deleted service']);
    }

    public function test_table_delete_and_detail_restore_use_shared_history_preserving_actions(): void
    {
        $product = Product::factory()->create(['name' => 'Service with retained history']);
        $original = $product->fresh()->getAttributes();
        Livewire::test(ListProducts::class)->callAction(TestAction::make('deleteRecord')->table($product));
        $this->assertTrue(RecordVisibility::isDeleted($product->fresh()));
        $this->assertSame($original, $product->fresh()->getAttributes());
        Livewire::test(ViewProduct::class, ['record' => $product->id])->callAction('restoreRecord');
        $this->assertFalse(RecordVisibility::isDeleted($product->fresh()));
        $this->assertSame($original, $product->fresh()->getAttributes());
    }

    public function test_edit_form_opened_before_deletion_cannot_save_business_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Retained service']);
        $editor = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['name' => 'Stale edit']);
        app(RecordVisibility::class)->delete($this->administrator, $product);
        $editor->call('save')->assertForbidden();
        $this->assertSame('Retained service', $product->fresh()->name);
    }

    public function test_deleted_category_is_inspectable_but_direct_edit_is_forbidden(): void
    {
        $category = ProductCategory::create(['name' => 'Retained category']);
        app(RecordVisibility::class)->delete($this->administrator, $category);
        Livewire::test(ListProductCategories::class)->assertCanNotSeeTableRecords([$category]);
        Livewire::test(ListProductCategories::class)->filterTable('record_visibility', 'all')->assertCanSeeTableRecords([$category]);
        Livewire::test(ViewProductCategory::class, ['record' => $category->id])->assertSee('Deleted — retained for history.')->assertActionHidden('edit');
        Livewire::test(EditProductCategory::class, ['record' => $category->id])->assertForbidden();
    }

    public function test_deleted_user_has_read_only_details_and_restore_returns_edit_access(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        app(RecordVisibility::class)->delete($this->administrator, $customer);
        Livewire::test(ListUsers::class)->assertCanNotSeeTableRecords([$customer]);
        Livewire::test(ListUsers::class)->filterTable('record_visibility', 'deleted')->assertCanSeeTableRecords([$customer]);
        Livewire::test(ViewUser::class, ['record' => $customer->id])->assertSee($customer->email)->assertSee('Deleted — retained for history.')->assertActionHidden('edit');
        Livewire::test(EditUser::class, ['record' => $customer->id])->assertForbidden();
        app(RecordVisibility::class)->restore($this->administrator, $customer);
        Livewire::test(EditUser::class, ['record' => $customer->id])->assertSuccessful();
        $this->assertTrue($customer->fresh()->hasRole(UserRole::Customer->value));
    }

    public function test_local_role_resource_preserves_permission_form_and_only_soft_deletes_roles(): void
    {
        $role = Role::create(['name' => 'synthetic_review_role', 'guard_name' => 'web']);
        $role->givePermissionTo('ViewAny:Product');
        $list = Livewire::test(ListRoles::class)->assertSuccessful();
        $table = $list->instance()->getTable();
        $this->assertArrayNotHasKey('delete', $table->getFlatActions());
        $this->assertArrayNotHasKey('delete', $table->getFlatBulkActions());
        $this->assertSame([], $table->getToolbarActions());
        $list->callAction(TestAction::make('deleteRecord')->table($role));
        $this->assertTrue(RecordVisibility::isDeleted($role->fresh()));
        Livewire::test(ListRoles::class)->assertCanNotSeeTableRecords([$role]);
        Livewire::test(ListRoles::class)->filterTable('record_visibility', 'deleted')->assertCanSeeTableRecords([$role]);
        Livewire::test(ViewRole::class, ['record' => $role->id])->assertSee('Deleted — retained for history.')->assertActionHidden('edit')->assertActionDoesNotExist('delete')->callAction('restoreRecord');
        $this->assertFalse(RecordVisibility::isDeleted($role->fresh()));
        Livewire::test(EditRole::class, ['record' => $role->id])->assertSuccessful()->assertActionDoesNotExist('delete')->fillForm(['name' => 'synthetic_review_role_edited'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('synthetic_review_role_edited', $role->fresh()->name);
        $this->assertTrue($role->fresh()->hasPermissionTo('ViewAny:Product'));
    }

    public function test_deleted_role_rejects_direct_and_already_open_edits(): void
    {
        $role = Role::create(['name' => 'synthetic_closed_role', 'guard_name' => 'web']);
        $editor = Livewire::test(EditRole::class, ['record' => $role->id])->fillForm(['name' => 'stale_role_edit']);
        app(RecordVisibility::class)->delete($this->administrator, $role);
        $editor->call('save')->assertForbidden();
        Livewire::test(EditRole::class, ['record' => $role->id])->assertForbidden();
        $this->assertSame('synthetic_closed_role', $role->fresh()->name);
    }

    public function test_user_role_options_only_include_active_roles(): void
    {
        $active = Role::create(['name' => 'synthetic_active_role', 'guard_name' => 'web']);
        $deleted = Role::create(['name' => 'synthetic_deleted_role', 'guard_name' => 'web']);
        app(RecordVisibility::class)->delete($this->administrator, $deleted);
        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);
        Livewire::test(EditUser::class, ['record' => $customer->id])->assertFormFieldExists('roles', function ($field) use ($active, $deleted): bool {
            $options = $field->getOptions();

            return array_key_exists($active->name, $options) && ! array_key_exists($deleted->name, $options);
        });
    }

    public function test_price_history_visibility_preserves_the_current_offer_and_original_amount(): void
    {
        $product = Product::factory()->published()->create();
        $price = ProductPrice::factory()->create(['product_id' => $product->id]);
        ProductTerms::factory()->create(['product_id' => $product->id]);
        $product->forceFill(['is_available' => true])->save();
        $original = $price->fresh()->getAttributes();
        $parameters = ['ownerRecord' => $product, 'pageClass' => ViewProduct::class];
        $manager = PricesRelationManager::class;
        Livewire::test($manager, $parameters)->assertCanSeeTableRecords([$price])->callAction(TestAction::make('deleteRecord')->table($price));
        Livewire::test($manager, $parameters)->assertCanNotSeeTableRecords([$price]);
        $this->assertSame($original, $price->fresh()->getAttributes());
        $this->assertSame($price->id, $product->currentPrice()->first()->id);
        $this->assertTrue($product->fresh()->hasConfiguredOffer());
        Livewire::test($manager, $parameters)->filterTable('record_visibility', 'deleted')->assertCanSeeTableRecords([$price])->callAction(TestAction::make('restoreRecord')->table($price));
        Livewire::test($manager, $parameters)->assertCanSeeTableRecords([$price]);
        $this->assertSame($original, $price->fresh()->getAttributes());
    }

    public function test_deleted_package_delivery_hides_diagnostics_without_changing_fulfillment(): void
    {
        $checkout = PackageCheckout::create([
            'operation_id' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64), 'proof_hash' => str_repeat('b', 64),
            'package_code' => 'synthetic', 'package_name' => 'Synthetic retained package',
            'amount_minor' => 77700, 'currency' => 'PHP', 'quantity' => 1, 'offer_version' => 'synthetic',
            'business_name' => 'Synthetic customer', 'email' => 'synthetic@example.test',
            'provider_mode' => 'test', 'status' => 'paid', 'creation_phase' => 'complete', 'paid_at' => now(),
        ]);
        $capture = PackagePurchaseDelivery::create([
            'package_checkout_id' => $checkout->id, 'phase' => 'pending', 'payload' => 'synthetic capture',
            'payload_hash' => str_repeat('c', 64), 'status' => 'delivered', 'attempts' => 424242,
        ]);
        $purchase = PackagePurchaseDelivery::create([
            'package_checkout_id' => $checkout->id, 'phase' => 'completed', 'payload' => 'synthetic completion',
            'payload_hash' => str_repeat('d', 64), 'status' => 'delivered', 'attempts' => 535353,
        ]);
        $original = $purchase->fresh()->getAttributes();
        $page = ViewPackageCheckout::class;
        Livewire::test($page, ['record' => $checkout->id])->assertSee('424242')->assertSee('535353');
        app(RecordVisibility::class)->delete($this->administrator, $capture);
        Livewire::test($page, ['record' => $checkout->id])->assertDontSee('424242')->assertSee('535353')->assertSee('Diagnostics hidden.');
        app(RecordVisibility::class)->delete($this->administrator, $purchase);
        Livewire::test($page, ['record' => $checkout->id])->assertDontSee('424242')->assertDontSee('535353')
            ->assertSee('Deleted')->assertSee('package-deliveries');
        $record = PackageCheckoutResource::getEloquentQuery()->findOrFail($checkout->id);
        $this->assertSame('delivered', $record->fulfillmentStatus());
        $this->assertSame('delivered', $record->purchaseDelivery->status);
        $this->assertSame($original, $purchase->fresh()->getAttributes());
        $list = Livewire::test(ListPackageCheckouts::class);
        $column = $list->instance()->getTable()->getColumn('purchaseDelivery.status');
        $this->assertSame('Deleted', $column->record($record)->getState());
    }
}
