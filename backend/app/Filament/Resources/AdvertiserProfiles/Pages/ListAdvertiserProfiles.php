<?php

namespace App\Filament\Resources\AdvertiserProfiles\Pages;

use App\Filament\Resources\AdvertiserProfiles\AdvertiserProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdvertiserProfiles extends ListRecords
{
    protected static string $resource = AdvertiserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
