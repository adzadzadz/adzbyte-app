<?php

namespace App\Support\Filament;

use Filament\Navigation\NavigationItem;

trait HasLockedCommerceNavigation
{
    /** @return array<NavigationItem> */
    public static function getNavigationItems(): array
    {
        return array_map(CommerceNavigation::lock(...), parent::getNavigationItems());
    }
}
