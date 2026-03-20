<?php

namespace App\Filament\Resources\AdPlacementPolicies\Pages;

use App\Filament\Resources\AdPlacementPolicies\AdPlacementPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdPlacementPolicies extends ListRecords
{
    protected static string $resource = AdPlacementPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
