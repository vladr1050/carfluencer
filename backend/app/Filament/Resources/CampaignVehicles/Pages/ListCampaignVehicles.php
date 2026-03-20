<?php

namespace App\Filament\Resources\CampaignVehicles\Pages;

use App\Filament\Resources\CampaignVehicles\CampaignVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampaignVehicles extends ListRecords
{
    protected static string $resource = CampaignVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
