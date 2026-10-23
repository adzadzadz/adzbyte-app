<?php

namespace App\Filament\Account\Pages;

class Services extends Projects
{
    protected static ?string $navigationLabel = 'Services';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected string $workType = 'service';
}
