<?php

namespace App\Filament\Resources\AdvertiserProfiles;

use App\Filament\Resources\AdvertiserProfiles\Pages\CreateAdvertiserProfile;
use App\Filament\Resources\AdvertiserProfiles\Pages\EditAdvertiserProfile;
use App\Filament\Resources\AdvertiserProfiles\Pages\ListAdvertiserProfiles;
use App\Filament\Resources\AdvertiserProfiles\Schemas\AdvertiserProfileForm;
use App\Filament\Resources\AdvertiserProfiles\Tables\AdvertiserProfilesTable;
use App\Models\AdvertiserProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdvertiserProfileResource extends Resource
{
    protected static ?string $model = AdvertiserProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    public static function form(Schema $schema): Schema
    {
        return AdvertiserProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdvertiserProfilesTable::configure($table);
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
            'index' => ListAdvertiserProfiles::route('/'),
            'create' => CreateAdvertiserProfile::route('/create'),
            'edit' => EditAdvertiserProfile::route('/{record}/edit'),
        ];
    }
}
