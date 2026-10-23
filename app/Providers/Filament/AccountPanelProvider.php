<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EmailVerificationPrompt;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Http\Middleware\RejectDeletedWebIdentity;
use App\Support\Filament\AdzbytePanel;
use App\Support\Filament\CommerceNavigation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AccountPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return AdzbytePanel::configure($panel, AdzbytePanel::CUSTOMER_ACCOUNT)
            ->id('account')
            ->path('')
            ->login()
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->emailVerification(EmailVerificationPrompt::class)
            ->emailChangeVerification()
            ->profile()
            ->navigationItems([
                CommerceNavigation::lock(NavigationItem::make('Orders')->url(fn () => route('orders.index'))->icon('heroicon-o-document-text')->group('Ecommerce · Under development')->sort(12)),
                CommerceNavigation::lock(NavigationItem::make('Cart')->url(fn () => route('cart.show'))->icon('heroicon-o-shopping-cart')->group('Ecommerce · Under development')->sort(11)),
                NavigationItem::make('Profile & security')->url(fn () => filament()->getProfileUrl())->icon('heroicon-o-user-circle')->sort(5),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('auth.registration-link'))
            ->discoverResources(in: app_path('Filament/Account/Resources'), for: 'App\Filament\Account\Resources')
            ->discoverPages(in: app_path('Filament/Account/Pages'), for: 'App\Filament\Account\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Account/Widgets'), for: 'App\Filament\Account\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                RejectDeletedWebIdentity::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
