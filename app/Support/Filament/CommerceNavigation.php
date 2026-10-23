<?php

namespace App\Support\Filament;

use Filament\Navigation\NavigationItem;

final class CommerceNavigation
{
    public const MESSAGE = 'This feature is under development.';

    public static function locked(): bool
    {
        return (bool) config('commerce.features_locked', true);
    }

    public static function lock(NavigationItem $item): NavigationItem
    {
        if (! self::locked()) {
            return $item;
        }

        return $item->url(null)->icon('heroicon-o-lock-closed')->activeIcon('heroicon-o-lock-closed')
            ->isActiveWhen(fn (): bool => false)
            ->extraAttributes([
                'data-commerce-locked' => '',
                'role' => 'button',
                'tabindex' => '0',
                'aria-disabled' => 'true',
                'aria-label' => $item->getLabel().'. Locked. '.self::MESSAGE,
                'x-data' => '{}',
                'x-tooltip' => "{ content: '".self::MESSAGE."', theme: \$store.theme, trigger: 'mouseenter focus' }",
                'x-on:click.capture.prevent.stop' => "\$tooltip('".self::MESSAGE."', { theme: \$store.theme })",
                'x-on:keydown.enter.prevent.stop' => "\$tooltip('".self::MESSAGE."', { theme: \$store.theme })",
                'x-on:keydown.space.prevent.stop' => "\$tooltip('".self::MESSAGE."', { theme: \$store.theme })",
            ], merge: true);
    }
}
