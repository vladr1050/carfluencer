<?php

namespace App\Filament\Resources\GeoZones\Pages;

use App\Filament\Resources\GeoZones\GeoZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeoZones extends ListRecords
{
    protected static string $resource = GeoZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
