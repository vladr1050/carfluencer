<?php

namespace App\Filament\Resources\CampaignProofs\Pages;

use App\Filament\Resources\CampaignProofs\CampaignProofResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampaignProofs extends ListRecords
{
    protected static string $resource = CampaignProofResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
