<?php

namespace App\Filament\Pages;

use App\Services\Portal\PortalRead;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Operations';

    protected string $view = 'filament.pages.dashboard';

    protected function getViewData(): array
    {
        return ['portalOverview' => app(PortalRead::class)->staffHome(auth()->user())];
    }

    /**
     * @return array<never>
     */
    public function getWidgets(): array
    {
        return [];
    }
}
