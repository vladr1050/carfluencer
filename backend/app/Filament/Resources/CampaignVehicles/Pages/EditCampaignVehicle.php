<?php

namespace App\Filament\Resources\CampaignVehicles\Pages;

use App\Filament\Resources\CampaignVehicles\CampaignVehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaignVehicle extends EditRecord
{
    protected static string $resource = CampaignVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
