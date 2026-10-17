<?php

namespace Tests\Feature;

use App\Actions\Catalog\CatalogActions;
use App\Enums\ProductCapability;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\Products\RelationManagers\PricesRelationManager;
use App\Filament\Resources\Products\RelationManagers\TermsRelationManager;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use App\Models\User;
use App\Support\Filament\CatalogInput;
use BezhanSalleh\FilamentShield\FilamentShield;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guests_customers_and_unprivileged_administrators_cannot_access_catalog_pages(): void
    {
        $product = Product::factory()->create();
        $paths = ['/admin/products', '/admin/products/create', '/admin/products/'.$product->id, '/admin/products/'.$product->id.'/edit'];
        foreach ($paths as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        foreach ([UserRole::Customer, UserRole::Administrator] as $role) {
            $actor = $this->actor($role);
            if ($role === UserRole::Customer) {
                $actor->givePermissionTo(array_column(ProductCapability::cases(), 'value'));
            }
            foreach ($paths as $path) {
                $this->actingAs($actor)->get($path)->assertForbidden();
            }
        }
        $this->assertDatabaseCount('product_changes', 0);
    }

    public function test_empty_catalog_and_multiple_incomplete_drafts_work_without_seeded_offers(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        Livewire::test(ListProducts::class)->assertCountTableRecords(0);
        Livewire::test(CreateProduct::class)->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateProduct::class)->fillForm(['name' => 'Second synthetic service'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_prices', 0);
        $this->assertDatabaseCount('product_terms', 0);
        foreach (Product::all() as $product) {
            $this->assertSame(ProductStatus::Draft, $product->status);
            $this->assertFalse($product->is_available);
        }
        Livewire::test(ListProducts::class)->searchTable('Second synthetic')->assertCountTableRecords(1);
    }

    public function test_content_updates_preserve_identity_and_structured_media_and_reject_unsafe_references(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create(['media' => ['legacy' => ['src' => '/images/old.webp'], 'cover' => '/images/cover.webp']]);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['name' => 'Updated', 'media' => ['cover' => 'https://example.test/image.webp']])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated', $product->fresh()->name);
        $this->assertSame($product->public_id, $product->fresh()->public_id);
        $this->assertSame(['src' => '/images/old.webp'], $product->fresh()->media['legacy']);
        $this->assertSame('https://example.test/image.webp', $product->fresh()->media['cover']);
        foreach (['javascript:alert(1)', '//example.test/image', '/images/../secret', 'https://user:password@example.test/image'] as $unsafe) {
            Livewire::test(EditProduct::class, ['record' => $product->id])
                ->fillForm(['media' => ['cover' => $unsafe]])->call('save')->assertHasFormErrors();
        }
        $this->assertDatabaseCount('product_changes', 1);
    }

    public function test_duplicate_slugs_and_lifecycle_injection_do_not_mutate_protected_fields(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $one = Product::factory()->create(['slug' => 'taken']);
        $two = Product::factory()->create();
        Livewire::test(EditProduct::class, ['record' => $two->id])
            ->fillForm(['slug' => 'taken'])->call('save')->assertHasFormErrors(['slug' => 'unique']);
        Livewire::test(EditProduct::class, ['record' => $two->id])
            ->set('data.status', 'published')->set('data.is_available', true)->set('data.public_id', $one->public_id)
            ->fillForm(['name' => 'Safe edit'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(ProductStatus::Draft, $two->fresh()->status);
        $this->assertFalse($two->fresh()->is_available);
        $this->assertSame($two->public_id, $two->fresh()->public_id);
    }

    public function test_named_actions_cover_prices_terms_publication_availability_and_archive_restore(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create();
        $page = Livewire::test(ViewProduct::class, ['record' => $product->id]);
        $page->callAction('revisePrice', data: ['amount' => '1234.56'])->assertHasNoActionErrors();
        $page->callAction('reviseTerms', data: ['scope' => 'Synthetic scope', 'deliverables' => 'Synthetic deliverables'])->assertHasNoActionErrors();
        $page->callAction('publish')->assertHasNoActionErrors();
        $this->assertFalse($product->fresh()->is_available);
        $page->callAction('enableAvailability')->assertHasNoActionErrors();
        $this->assertTrue($product->fresh()->hasConfiguredOffer());
        $this->assertSame(123456, $product->currentPrice()->first()->amount_minor);
        $page->callAction('unpublish')->assertHasNoActionErrors();
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
        $this->assertFalse($product->fresh()->is_available);
        $page->callAction('archive')->assertHasNoActionErrors();
        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
        $page->assertActionHidden('publish')->callAction('restore')->assertHasNoActionErrors();
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
        $this->assertFalse($product->fresh()->is_available);
        $this->assertDatabaseCount('product_changes', 7);
    }

    public function test_incomplete_availability_and_publication_errors_are_visible_without_partial_writes(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $blank = Product::create([]);
        Livewire::test(ViewProduct::class, ['record' => $blank->id])
            ->callAction('publish')->assertNotified('Could not update product');
        $published = Product::factory()->published()->create();
        Livewire::test(ViewProduct::class, ['record' => $published->id])
            ->callAction('enableAvailability')->assertNotified('Could not update product');
        $this->assertSame(ProductStatus::Draft, $blank->fresh()->status);
        $this->assertFalse($published->fresh()->is_available);
        $this->assertDatabaseCount('product_changes', 0);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_price_inputs_do_not_create_versions(string $amount): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create();
        Livewire::test(ViewProduct::class, ['record' => $product->id])
            ->callAction('revisePrice', data: ['amount' => $amount])->assertHasActionErrors();
        $this->assertDatabaseCount('product_prices', 0);
        $this->assertDatabaseCount('product_changes', 0);
    }

    public static function invalidAmounts(): array
    {
        return [[''], ['-1'], ['1.001'], ['1e3'], ['1,000'], ['NaN'], ['92233720368547758.08']];
    }

    public function test_maximum_price_is_stored_and_displayed_without_float_rounding(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create();
        Livewire::test(ViewProduct::class, ['record' => $product->id])
            ->callAction('revisePrice', data: ['amount' => '92233720368547758.07'])
            ->assertHasNoActionErrors()->assertSee('PHP 92,233,720,368,547,758.07');
        $this->assertSame(PHP_INT_MAX, $product->currentPrice()->first()->amount_minor);
    }

    public function test_stale_success_receipts_reject_changed_payloads_with_visible_errors(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $this->actingAs($actor);
        $actions = app(CatalogActions::class);
        $page = Livewire::test(CreateProduct::class)->fillForm(['name' => 'First draft']);
        $product = $actions->create($actor, CatalogInput::content($page->get('data')), $page->get('operationId'));
        $page->fillForm(['name' => 'Changed draft'])->call('create')->assertHasFormErrors(['name']);
        $this->assertDatabaseCount('products', 1);
        $view = Livewire::test(ViewProduct::class, ['record' => $product->id]);
        $actions->revisePrice($actor, $product, ['amount_minor' => 100, 'currency' => 'PHP'], $view->get('operationIds')['revisePrice']);
        $view->callAction('revisePrice', data: ['amount' => '2.00'])->assertNotified('Could not update product');
        $this->assertDatabaseCount('product_prices', 1);
    }

    public function test_history_components_reject_direct_unauthorized_mounts_and_revoked_access(): void
    {
        $product = Product::factory()->create();
        $actor = $this->actor(UserRole::Administrator);
        $this->actingAs($actor);
        foreach ([PricesRelationManager::class, TermsRelationManager::class, ChangesRelationManager::class] as $manager) {
            Livewire::test($manager, ['ownerRecord' => $product, 'pageClass' => ViewProduct::class])->assertForbidden();
        }
        $actor->givePermissionTo('View:Product');
        $history = Livewire::test(PricesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => ViewProduct::class]);
        $actor->revokePermissionTo('View:Product');
        $history->call('$refresh')->assertForbidden();
    }

    public function test_product_content_and_history_render_untrusted_markup_as_text(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $payload = '<script>alert("catalog")</script>';
        $product = Product::factory()->create(['description' => $payload]);
        ProductTerms::factory()->create(['product_id' => $product->id, 'content' => ['scope' => $payload]]);
        $this->get('/admin/products/'.$product->id)->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    }

    public function test_terms_and_price_revisions_preserve_history_and_unknown_structured_sections(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create();
        $oldPrice = ProductPrice::factory()->create(['product_id' => $product->id]);
        $oldTerms = ProductTerms::factory()->create(['product_id' => $product->id, 'content' => ['scope' => 'Original', 'deliverables' => ['structured'], 'custom' => ['keep' => true]]]);
        Livewire::test(ViewProduct::class, ['record' => $product->id])
            ->callAction('revisePrice', data: ['amount' => '0'])->assertHasNoActionErrors()
            ->callAction('reviseTerms', data: ['scope' => 'Revised'])->assertHasNoActionErrors();
        $this->assertSame($oldPrice->amount_minor, $oldPrice->fresh()->amount_minor);
        $this->assertSame('Original', $oldTerms->fresh()->content['scope']);
        $this->assertSame('Revised', $product->currentTerms()->first()->content['scope']);
        $this->assertSame(['structured'], $product->currentTerms()->first()->content['deliverables']);
        $this->assertSame(['keep' => true], $product->currentTerms()->first()->content['custom']);
        $this->assertSame(0, $product->currentPrice()->first()->amount_minor);
    }

    public function test_view_only_staff_cannot_mount_mutations_or_access_create_and_edit(): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:Product', 'View:Product']);
        $this->actingAs($actor);
        $product = Product::factory()->create();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/'.$product->id)->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/products/'.$product->id.'/edit')->assertForbidden();
        $page = Livewire::test(ViewProduct::class, ['record' => $product->id]);
        foreach (['publish', 'revisePrice', 'reviseTerms', 'archive', 'editContent'] as $action) {
            $page->assertActionHidden($action)->call('mountAction', $action)->assertActionNotMounted($action);
        }
        $this->assertDatabaseCount('product_changes', 0);
    }

    public function test_price_staff_can_revise_prices_without_content_or_publication_access(): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:Product', 'View:Product', 'RevisePrice:Product']);
        $this->actingAs($actor);
        $product = Product::factory()->create();
        Livewire::test(ViewProduct::class, ['record' => $product->id])
            ->assertActionVisible('revisePrice')->assertActionHidden('reviseTerms')->assertActionHidden('publish')
            ->callAction('revisePrice', data: ['amount' => '0.01'])->assertHasNoActionErrors();
        $this->assertSame(1, $product->currentPrice()->first()->amount_minor);
    }

    public function test_lost_response_retries_reuse_create_and_revision_receipts(): void
    {
        $actor = $this->actor(UserRole::SuperAdmin);
        $this->actingAs($actor);
        $actions = app(CatalogActions::class);
        $creation = Livewire::test(CreateProduct::class)->fillForm(['name' => 'Retry draft']);
        $product = $actions->create($actor, CatalogInput::content($creation->get('data')), $creation->get('operationId'));
        $creation->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('products', 1);
        $page = Livewire::test(ViewProduct::class, ['record' => $product->id]);
        $id = $page->get('operationIds')['revisePrice'];
        $actions->revisePrice($actor, $product, ['amount_minor' => 10000, 'currency' => 'PHP'], $id);
        $page->callAction('revisePrice', data: ['amount' => '100.00'])->assertHasNoActionErrors();
        $this->assertDatabaseCount('product_prices', 1);
        $this->assertNotSame($id, $page->get('operationIds')['revisePrice']);
        $this->assertDatabaseCount('product_changes', 2);
    }

    public function test_content_and_terms_retries_commit_once_and_recheck_revoked_capabilities(): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:Product', 'View:Product', 'Update:Product', 'ReviseTerms:Product']);
        $this->actingAs($actor);
        $product = Product::factory()->create();
        $actions = app(CatalogActions::class);
        $edit = Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm(['name' => 'Retried content']);
        $id = $edit->get('operationId');
        $actions->update($actor, $product, CatalogInput::content($edit->get('data'), $product), $id);
        $edit->call('save')->assertHasNoFormErrors();
        $this->assertNotSame($id, $edit->get('operationId'));
        $this->assertDatabaseCount('product_changes', 1);
        $actor->revokePermissionTo('Update:Product');
        $edit->call('save')->assertForbidden();

        $view = Livewire::test(ViewProduct::class, ['record' => $product->id]);
        $actions->reviseTerms($actor, $product, ['scope' => 'Retried scope'], $view->get('operationIds')['reviseTerms']);
        $view->callAction('reviseTerms', data: ['scope' => 'Retried scope'])->assertHasNoActionErrors();
        $this->assertDatabaseCount('product_terms', 1);
        $this->assertDatabaseCount('product_changes', 2);
        $actor->revokePermissionTo('ReviseTerms:Product');
        $view->call('mountAction', 'reviseTerms')->assertActionNotMounted('reviseTerms');
        $this->assertDatabaseCount('product_changes', 2);
    }

    public function test_catalog_filters_and_create_only_access_respect_separate_capabilities(): void
    {
        $actor = $this->actor(UserRole::Administrator);
        $actor->givePermissionTo(['ViewAny:Product', 'Create:Product']);
        $this->actingAs($actor);
        Livewire::test(CreateProduct::class)->fillForm(['name' => 'Create-only draft'])->call('create')
            ->assertHasNoFormErrors()->assertRedirect(ProductResource::getUrl('index'));
        $draft = Product::query()->sole();
        $this->get('/admin/products/'.$draft->id)->assertForbidden();
        $published = Product::factory()->published()->create();
        ProductPrice::factory()->create(['product_id' => $published->id]);
        ProductTerms::factory()->create(['product_id' => $published->id]);
        $published->forceFill(['is_available' => true])->save();
        Livewire::test(ListProducts::class)->filterTable('status', 'draft')->assertCanSeeTableRecords([$draft])->assertCountTableRecords(1);
        Livewire::test(ListProducts::class)->filterTable('is_available', true)->assertCanSeeTableRecords([$published])->assertCountTableRecords(1);
    }

    public function test_history_is_read_only_and_catalog_has_no_destructive_routes_or_actions(): void
    {
        $this->actingAs($this->actor(UserRole::SuperAdmin));
        $product = Product::factory()->create();
        foreach ([PricesRelationManager::class, TermsRelationManager::class, ChangesRelationManager::class] as $manager) {
            $component = Livewire::test($manager, ['ownerRecord' => $product, 'pageClass' => ViewProduct::class]);
            $component->assertActionDoesNotExist('create')->assertActionDoesNotExist('edit')->assertActionDoesNotExist('delete');
        }
        $this->assertSame(['index', 'create', 'view', 'edit'], array_keys(ProductResource::getPages()));
        Livewire::test(ViewProduct::class, ['record' => $product->id])->assertActionDoesNotExist('delete');
    }

    public function test_catalog_permissions_are_discoverable_without_destructive_capabilities_or_default_grants(): void
    {
        $permissions = app(FilamentShield::class)->getEntitiesPermissions();
        foreach (ProductCapability::cases() as $capability) {
            $this->assertContains($capability->value, $permissions);
        }
        $this->assertNotContains('Delete:Product', $permissions);
        $this->assertNotContains('ForceDelete:Product', $permissions);
        $this->assertDatabaseCount('role_has_permissions', 0);
    }

    private function actor(UserRole $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($role->value);

        return $actor;
    }
}
