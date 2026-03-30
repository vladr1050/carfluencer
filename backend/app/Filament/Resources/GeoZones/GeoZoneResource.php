<?php

namespace App\Filament\Resources\GeoZones;

use App\Filament\Resources\GeoZones\Pages\CreateGeoZone;
use App\Filament\Resources\GeoZones\Pages\EditGeoZone;
use App\Filament\Resources\GeoZones\Pages\ListGeoZones;
use App\Filament\Resources\GeoZones\Schemas\GeoZoneForm;
use App\Filament\Resources\GeoZones\Tables\GeoZonesTable;
use App\Models\GeoZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GeoZoneResource extends Resource
{
    protected static ?string $model = GeoZone::class;

    protected static ?string $navigationLabel = 'Geo zones';

    protected static ?string $modelLabel = 'Geo zone';

    protected static ?string $pluralModelLabel = 'Geo zones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Telematics';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return GeoZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeoZonesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeoZones::route('/'),
            'create' => CreateGeoZone::route('/create'),
            'edit' => EditGeoZone::route('/{record}/edit'),
        ];
    }
}
