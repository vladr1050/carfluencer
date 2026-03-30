<?php

namespace App\Filament\Resources\GeoZones\Pages;

use App\Filament\Resources\GeoZones\GeoZoneResource;
use App\Models\GeoZone;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoZone extends EditRecord
{
    protected static string $resource = GeoZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        GeoZone::validateBoundingBox($data);

        return $data;
    }
}
