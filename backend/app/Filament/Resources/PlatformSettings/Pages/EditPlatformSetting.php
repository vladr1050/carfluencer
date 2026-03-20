<?php

namespace App\Filament\Resources\PlatformSettings\Pages;

use App\Filament\Resources\PlatformSettings\PlatformSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformSetting extends EditRecord
{
    protected static string $resource = PlatformSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
