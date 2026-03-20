<?php

namespace App\Filament\Resources\CampaignProofs\Pages;

use App\Filament\Resources\CampaignProofs\CampaignProofResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaignProof extends EditRecord
{
    protected static string $resource = CampaignProofResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
