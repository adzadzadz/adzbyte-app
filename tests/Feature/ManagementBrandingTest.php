<?php

namespace Tests\Feature;

use App\Support\Filament\AdzbytePanel;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Widgets\FilamentInfoWidget;
use Tests\TestCase;

class ManagementBrandingTest extends TestCase
{
    public function test_panels_share_the_brand_foundation_and_keep_explicit_identity(): void
    {
        $account = Filament::getPanel('account');
        $admin = Filament::getPanel('admin');

        $this->assertSame('', $account->getPath());
        $this->assertSame('admin', $admin->getPath());
        $this->assertSame(AdzbytePanel::CUSTOMER_ACCOUNT, $account->getBrandName());
        $this->assertSame(AdzbytePanel::ADMINISTRATION, $admin->getBrandName());

        foreach ([$account, $admin] as $panel) {
            $this->assertTrue($panel->hasDarkMode());
            $this->assertTrue($panel->hasDarkModeForced());
            $this->assertFalse($panel->hasThemeSwitcher());
            $this->assertSame('Poppins', $panel->getFontFamily());
            $this->assertSame(LocalFontProvider::class, $panel->getFontProvider());
            $this->assertSame('resources/css/filament/theme.css', $panel->getViteTheme());
            $this->assertNotContains(FilamentInfoWidget::class, $panel->getWidgets());
            $this->assertStringEndsWith(
                '/images/brand/adzbyte-logo-transparent.png',
                (string) $panel->getBrandLogo(),
            );
            $this->assertStringEndsWith(
                '/images/brand/adzbyte-logo-square-dark.png',
                (string) $panel->getFavicon(),
            );
        }
    }

    public function test_authentication_pages_render_the_correct_brand_context(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Customer account')
            ->assertSee('images/brand/adzbyte-logo-transparent.png', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Adzbyte administration')
            ->assertSee('images/brand/adzbyte-logo-transparent.png', false);
    }

    public function test_versioned_brand_assets_match_the_manifest(): void
    {
        $manifest = json_decode(
            file_get_contents(resource_path('brand/assets.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertCount(3, $manifest['assets']);

        foreach ($manifest['assets'] as $asset) {
            $path = base_path($asset['destination']);

            $this->assertFileExists($path);
            $this->assertSame($asset['sha256'], hash_file('sha256', $path));
        }
    }
}
