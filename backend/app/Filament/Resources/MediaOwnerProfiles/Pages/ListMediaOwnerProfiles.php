<?php

namespace App\Filament\Resources\MediaOwnerProfiles\Pages;

use App\Filament\Resources\MediaOwnerProfiles\MediaOwnerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaOwnerProfiles extends ListRecords
{
    protected static string $resource = MediaOwnerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
