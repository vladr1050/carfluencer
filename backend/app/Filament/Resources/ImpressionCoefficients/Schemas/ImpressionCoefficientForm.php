<?php

namespace App\Filament\Resources\ImpressionCoefficients\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ImpressionCoefficientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->required()
                    ->maxLength(32)
                    ->helperText('Bump when changing coefficients (tracked in impression snapshots).'),
                TextInput::make('vehicle_visibility_share')->numeric()->required()->step(0.000001),
                TextInput::make('pedestrian_visibility_share')->numeric()->required()->step(0.000001),
                TextInput::make('pedestrian_parking_share')->numeric()->required()->step(0.000001),
                TextInput::make('roadside_vehicle_share')->numeric()->required()->step(0.000001),
                TextInput::make('speed_factor_low')->numeric()->required()->step(0.0001),
                TextInput::make('speed_factor_medium')->numeric()->required()->step(0.0001),
                TextInput::make('speed_factor_high')->numeric()->required()->step(0.0001),
                TextInput::make('speed_factor_very_high')->numeric()->required()->step(0.0001),
                TextInput::make('dwell_factor_short')->numeric()->required()->step(0.0001),
                TextInput::make('dwell_factor_medium')->numeric()->required()->step(0.0001),
                TextInput::make('dwell_factor_long')->numeric()->required()->step(0.0001),
            ]);
    }
}
