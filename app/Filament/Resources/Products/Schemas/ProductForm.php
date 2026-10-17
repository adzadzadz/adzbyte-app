<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\Filament\CatalogInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Content')->description('Drafts may be incomplete. Publish and availability are managed separately.')->schema([
                TextInput::make('name')->maxLength(255),
                TextInput::make('slug')->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->helperText('Optional lowercase words separated by hyphens.'),
                Textarea::make('summary')->rows(3)->columnSpanFull(),
                Textarea::make('description')->rows(6)->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Images')->schema([
                KeyValue::make('media')->label('Named image references')->keyLabel('Name')->valueLabel('Image URL or path')
                    ->rules(['array', fn () => CatalogInput::mediaRule()])->default([])
                    ->helperText('Use HTTPS URLs or /images/ paths. Existing structured media is retained. No upload is required.'),
            ]),
        ]);
    }
}
