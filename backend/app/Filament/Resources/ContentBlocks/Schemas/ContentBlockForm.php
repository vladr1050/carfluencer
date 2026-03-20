<?php

namespace App\Filament\Resources\ContentBlocks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContentBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
