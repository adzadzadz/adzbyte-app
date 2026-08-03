<?php

namespace App\Filament\Account\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected string $view = 'filament.account.pages.dashboard';

    /**
     * @return array<never>
     */
    public function getWidgets(): array
    {
        return [];
    }
}
