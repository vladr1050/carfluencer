<?php

namespace App\Filament\Resources\MediaOwnerProfiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MediaOwnerProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('company_name')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('registration_number'),
                Textarea::make('address')
                    ->columnSpanFull(),
            ]);
    }
}
