<?php

namespace App\Filament\Resources\MediaOwnerProfiles;

use App\Filament\Resources\MediaOwnerProfiles\Pages\CreateMediaOwnerProfile;
use App\Filament\Resources\MediaOwnerProfiles\Pages\EditMediaOwnerProfile;
use App\Filament\Resources\MediaOwnerProfiles\Pages\ListMediaOwnerProfiles;
use App\Filament\Resources\MediaOwnerProfiles\Schemas\MediaOwnerProfileForm;
use App\Filament\Resources\MediaOwnerProfiles\Tables\MediaOwnerProfilesTable;
use App\Models\MediaOwnerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MediaOwnerProfileResource extends Resource
{
    protected static ?string $model = MediaOwnerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Directory';

    public static function form(Schema $schema): Schema
    {
        return MediaOwnerProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaOwnerProfilesTable::configure($table);
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
            'index' => ListMediaOwnerProfiles::route('/'),
            'create' => CreateMediaOwnerProfile::route('/create'),
            'edit' => EditMediaOwnerProfile::route('/{record}/edit'),
        ];
    }
}
