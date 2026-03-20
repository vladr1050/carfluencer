<?php

namespace App\Filament\Resources\MediaOwnerProfiles\Pages;

use App\Filament\Resources\MediaOwnerProfiles\MediaOwnerProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMediaOwnerProfile extends EditRecord
{
    protected static string $resource = MediaOwnerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
