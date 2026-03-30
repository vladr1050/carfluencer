<?php

namespace App\Filament\Resources\GeoZones\Schemas;

use App\Services\Telemetry\HeatmapLeafletStyle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
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
                Section::make('Zone geometry (WGS84)')
                    ->description('Parking sessions match when their center lies inside the polygon drawn on the map, or inside the bounding box if no polygon is set. Numeric fields are the axis-aligned envelope (south ≤ lat ≤ north, west ≤ lng ≤ east); they update when you draw. Use “Refresh map from fields” to replace the shape with a rectangle from the numbers (clears the polygon).')
                    ->schema([
                        Hidden::make('polygon_geojson'),
                        TextInput::make('min_lat')
                            ->label('South (min latitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(56.90)
                            ->extraInputAttributes(['id' => 'geo-zone-input-min_lat']),
                        TextInput::make('max_lat')
                            ->label('North (max latitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(57.05)
                            ->extraInputAttributes(['id' => 'geo-zone-input-max_lat']),
                        TextInput::make('min_lng')
                            ->label('West (min longitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(23.95)
                            ->extraInputAttributes(['id' => 'geo-zone-input-min_lng']),
                        TextInput::make('max_lng')
                            ->label('East (max longitude)')
                            ->required()
                            ->numeric()
                            ->step(0.0000001)
                            ->default(24.25)
                            ->extraInputAttributes(['id' => 'geo-zone-input-max_lng']),
                        SchemaView::make('filament.resources.geo-zones.components.geo-zone-map-picker')
                            ->key('geoZoneMapPicker')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->viewData(function (Get $get): array {
                                return [
                                    'tileLayer' => HeatmapLeafletStyle::tileLayerConfig(),
                                    'initial' => [
                                        'min_lat' => $get('min_lat'),
                                        'max_lat' => $get('max_lat'),
                                        'min_lng' => $get('min_lng'),
                                        'max_lng' => $get('max_lng'),
                                        'polygon_geojson' => $get('polygon_geojson'),
                                    ],
                                ];
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}
