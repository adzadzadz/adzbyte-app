<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AccountPanelProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AccountPanelProvider::class,
    AdminPanelProvider::class,
];
