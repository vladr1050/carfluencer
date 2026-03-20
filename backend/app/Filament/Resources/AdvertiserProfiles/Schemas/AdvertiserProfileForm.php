<?php

namespace App\Filament\Resources\AdvertiserProfiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AdvertiserProfileForm
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
                TextInput::make('discount_percent')
                    ->numeric(),
                TextInput::make('agency_commission_percent')
                    ->numeric(),
            ]);
    }
}
