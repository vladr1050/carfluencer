<?php

namespace App\Filament\Resources\AdPlacementPolicies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdPlacementPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('size_class')
                    ->label('Ad placement size class')
                    ->options([
                        'S' => 'S — small',
                        'M' => 'M — medium',
                        'L' => 'L — large',
                        'XL' => 'XL — extra large',
                    ])
                    ->required(),
                TextInput::make('base_price')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                Toggle::make('active')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
