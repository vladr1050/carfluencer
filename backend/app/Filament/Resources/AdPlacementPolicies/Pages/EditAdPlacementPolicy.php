<?php

namespace App\Filament\Resources\AdPlacementPolicies\Pages;

use App\Filament\Resources\AdPlacementPolicies\AdPlacementPolicyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdPlacementPolicy extends EditRecord
{
    protected static string $resource = AdPlacementPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
