<?php

namespace App\Filament\Pages\Portal;

use App\Support\Filament\SupportPage;

final class CustomerSupport extends SupportPage
{
    protected static bool $staffMode = true;

    protected static ?string $navigationLabel = 'Customer support';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Customer portal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
}
