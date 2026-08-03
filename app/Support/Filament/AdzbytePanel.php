<?php

namespace App\Support\Filament;

use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

final class AdzbytePanel
{
    public const ADMINISTRATION = 'Adzbyte administration';

    public const CUSTOMER_ACCOUNT = 'Customer account';

    /**
     * @var array<int, string>
     */
    private const MANAGEMENT_GRAY = [
        50 => '#f8fafc',
        100 => '#f1f5f9',
        200 => '#e2e8f0',
        300 => '#cbd5e1',
        400 => '#94a3b8',
        500 => '#64748b',
        600 => '#475569',
        700 => '#334155',
        800 => '#1b2535',
        900 => '#111827',
        950 => '#0a0f17',
    ];

    public static function configure(Panel $panel, string $context): Panel
    {
        return $panel
            ->brandName($context)
            ->brandLogo(asset('images/brand/adzbyte-logo-transparent.png'))
            ->darkModeBrandLogo(asset('images/brand/adzbyte-logo-transparent.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/brand/adzbyte-logo-square-dark.png'))
            ->colors([
                'gray' => self::MANAGEMENT_GRAY,
                'primary' => Color::hex('#8139ff'),
            ])
            ->font('Poppins', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/theme.css')
            ->darkMode(condition: true, isForced: true)
            ->themeSwitcher(false)
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): View => view('filament.branding.panel-context', [
                    'context' => $context,
                    'placement' => 'simple',
                ]),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE,
                fn (): View => view('filament.branding.panel-context', [
                    'context' => $context,
                    'placement' => 'page',
                ]),
            );
    }
}
