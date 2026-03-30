<?php

namespace App\Filament\Resources\GeoZones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeoZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(64)
                            ->alphaDash()
                            ->unique(table: 'geo_zones', column: 'code')
                            ->helperText('Stable key (e.g. RIGA-CENTER). Letters, numbers, dashes, underscores.'),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive zones are ignored for parking-by-zone reports and zone attribution.'),
                    ])
                    ->columns(2),
                Section::make('Bounding box (WGS84)')
                    ->description('Parking sessions are matched when their center point falls inside this rectangle (south ≤ lat ≤ north, west ≤ lng ≤ east).')
                    ->schema([
                        TextInput::make('min_lat')
                            ->label('South (min latitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(56.90),
                        TextInput::make('max_lat')
                            ->label('North (max latitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(57.05),
                        TextInput::make('min_lng')
                            ->label('West (min longitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(23.95),
                        TextInput::make('max_lng')
                            ->label('East (max longitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(24.25),
                    ])
                    ->columns(2),
            ]);
    }
}
