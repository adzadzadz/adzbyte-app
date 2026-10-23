<?php

namespace App\Filament\Account\Pages;

use App\Services\Portal\PortalRead;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Home';

    protected string $view = 'filament.account.pages.dashboard';

    protected function getViewData(): array
    {
        return ['overview' => app(PortalRead::class)->home(auth()->user())];
    }

    /**
     * @return array<never>
     */
    public function getWidgets(): array
    {
        return [];
    }
}
