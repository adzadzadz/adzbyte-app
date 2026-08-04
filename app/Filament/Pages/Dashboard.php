<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Operations';

    protected string $view = 'filament.pages.dashboard';

    /**
     * @return array<never>
     */
    public function getWidgets(): array
    {
        return [];
    }
}
