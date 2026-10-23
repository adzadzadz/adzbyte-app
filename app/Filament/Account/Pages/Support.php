<?php

namespace App\Filament\Account\Pages;

use App\Support\Filament\SupportPage;

final class Support extends SupportPage
{
    protected static ?string $navigationLabel = 'Requests & reports';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
}
