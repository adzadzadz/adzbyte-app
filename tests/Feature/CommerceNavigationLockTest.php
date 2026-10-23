<?php

namespace Tests\Feature;

use App\Filament\Account\Pages\CommercePreview;
use App\Filament\Pages\CommerceSettings;
use App\Filament\Pages\DataRecords;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PackageCheckouts\PackageCheckoutResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Http\Middleware\LockCommercePages;
use App\Providers\Filament\AccountPanelProvider;
use App\Support\Filament\CommerceNavigation;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Tests\Support\Portal\CreatesPortal;
use Tests\TestCase;

class CommerceNavigationLockTest extends TestCase
{
    use CreatesPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.features_locked' => true]);
        // Explicit customer items are built when the panel provider registers.
        app(PanelRegistry::class)->register((new AccountPanelProvider($this->app))->panel(Panel::make()));
        $this->setUpPortal();
    }

    public function test_every_commerce_navigation_item_is_inert_and_has_pointer_and_keyboard_feedback(): void
    {
        foreach (['account' => [CommercePreview::class], 'admin' => [ProductResource::class, ProductCategoryResource::class, OrderResource::class, PaymentResource::class, PackageCheckoutResource::class, CommerceSettings::class]] as $panelId => $classes) {
            $panel = Filament::getPanel($panelId);
            Filament::setCurrentPanel($panel);
            $this->actingAs($panelId === 'account' ? $this->portalCustomer : $this->portalAdmin);
            $items = [];
            foreach ($classes as $class) {
                $items = [...$items, ...$class::getNavigationItems()];
            }
            if ($panelId === 'account') {
                foreach ($panel->getNavigationItems() as $item) {
                    if (in_array($item->getLabel(), ['Cart', 'Orders'], true)) {
                        $items[] = $item;
                    }
                }
                $this->assertCount(3, $items);
            }
            foreach ($items as $item) {
                $this->assertNull($item->getUrl());
                $this->assertSame('heroicon-o-lock-closed', $item->getIcon());
                $attributes = $item->getExtraAttributes();
                $this->assertSame('true', $attributes['aria-disabled']);
                $this->assertSame('0', $attributes['tabindex']);
                foreach (['x-on:click.capture.prevent.stop', 'x-on:keydown.enter.prevent.stop', 'x-on:keydown.space.prevent.stop'] as $event) {
                    $this->assertStringContainsString(CommerceNavigation::MESSAGE, $attributes[$event]);
                }
                $html = view('filament-panels::components.sidebar.item', [
                    'url' => $item->getUrl(), 'label' => $item->getLabel(), 'icon' => $item->getIcon(),
                    'attributes' => new ComponentAttributeBag($attributes),
                ])->render();
                $this->assertStringNotContainsString('href=', $html);
                $this->assertStringNotContainsString('wire:navigate', $html);
                $this->assertStringContainsString('data-commerce-locked', $html);
                $document = new \DOMDocument;
                $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $element = $document->getElementsByTagName('li')->item(0);
                foreach (['x-tooltip', 'x-on:click.capture.prevent.stop', 'x-on:keydown.enter.prevent.stop', 'x-on:keydown.space.prevent.stop'] as $event) {
                    $this->assertSame($attributes[$event], $element->getAttribute($event));
                }
            }
            $this->assertContains(LockCommercePages::class, $panel->getMiddleware());
        }
    }

    public function test_direct_commerce_screen_urls_are_locked_while_portal_work_stays_available(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $this->actingAs($this->portalCustomer)->get('/')->assertOk()->assertSee('Commerce locked')->assertDontSee('Explore the preview');
        $this->get('/projects')->assertOk();
        $this->get('/services')->assertOk();
        $this->get('/support')->assertOk();
        foreach (['/commerce-preview', '/cart', '/cart/handoff', '/checkout', '/orders', '/orders/'.str_repeat('0', 26)] as $path) {
            $this->get($path)->assertStatus(503)->assertSee('Ecommerce is under development')->assertHeader('Cache-Control', 'no-store, private');
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->portalAdmin)->get('/admin')->assertOk();
        $this->get('/admin/client-work')->assertOk();
        $this->portalAdmin->givePermissionTo('Manage:DataRecords');
        $this->get(DataRecords::getUrl())->assertOk();
        foreach (['/admin/products', '/admin/products/create', '/admin/product-categories', '/admin/orders', '/admin/payments', '/admin/package-checkouts', '/admin/commerce-settings'] as $path) {
            $this->get($path)->assertStatus(503)->assertSee('href="/admin"', false);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_explicitly_unlocking_restores_the_original_navigation(): void
    {
        config(['commerce.features_locked' => false]);
        Filament::setCurrentPanel(Filament::getPanel('account'));
        $item = CommercePreview::getNavigationItems()[0];
        $this->assertSame(CommercePreview::getUrl(), $item->getUrl());
        $this->assertArrayNotHasKey('data-commerce-locked', $item->getExtraAttributes());
        $this->actingAs($this->portalCustomer)->get('/commerce-preview')->assertOk();
    }
}
