<?php

namespace App\Filament\Resources\GeoZones\Pages;

use App\Filament\Resources\GeoZones\GeoZoneResource;
use App\Models\GeoZone;
use Filament\Resources\Pages\CreateRecord;

class CreateGeoZone extends CreateRecord
{
    protected static string $resource = GeoZoneResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = GeoZone::normalizeGeometryFields($data);
        GeoZone::validateZoneGeometry($data);

        return $data;
    }
}
