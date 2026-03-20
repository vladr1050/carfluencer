<?php

namespace App\Filament\Resources\PlatformSettings;

use App\Filament\Resources\PlatformSettings\Pages\CreatePlatformSetting;
use App\Filament\Resources\PlatformSettings\Pages\EditPlatformSetting;
use App\Filament\Resources\PlatformSettings\Pages\ListPlatformSettings;
use App\Filament\Resources\PlatformSettings\Schemas\PlatformSettingForm;
use App\Filament\Resources\PlatformSettings\Tables\PlatformSettingsTable;
use App\Models\PlatformSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlatformSettingResource extends Resource
{
    protected static ?string $model = PlatformSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog & settings';

    public static function form(Schema $schema): Schema
    {
        return PlatformSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformSettings::route('/'),
            'create' => CreatePlatformSetting::route('/create'),
            'edit' => EditPlatformSetting::route('/{record}/edit'),
        ];
    }
}
