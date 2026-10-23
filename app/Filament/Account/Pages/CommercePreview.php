<?php

namespace App\Filament\Account\Pages;

use App\Support\Filament\HasLockedCommerceNavigation;
use Filament\Pages\Page;

final class CommercePreview extends Page
{
    use HasLockedCommerceNavigation;

    protected static ?string $title = 'Ecommerce preview';

    protected static ?string $navigationLabel = 'Ecommerce';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected string $view = 'filament.portal.commerce-preview';

    public static function getNavigationBadge(): ?string
    {
        return 'Under development';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
