<?php

namespace App\Filament\Resources\AdvertiserProfiles\Pages;

use App\Filament\Resources\AdvertiserProfiles\AdvertiserProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdvertiserProfile extends EditRecord
{
    protected static string $resource = AdvertiserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
