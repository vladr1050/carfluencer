<?php

namespace App\Filament\Resources\CampaignVehicles;

use App\Filament\Resources\CampaignVehicles\Pages\CreateCampaignVehicle;
use App\Filament\Resources\CampaignVehicles\Pages\EditCampaignVehicle;
use App\Filament\Resources\CampaignVehicles\Pages\ListCampaignVehicles;
use App\Filament\Resources\CampaignVehicles\Schemas\CampaignVehicleForm;
use App\Filament\Resources\CampaignVehicles\Tables\CampaignVehiclesTable;
use App\Models\CampaignVehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignVehicleResource extends Resource
{
    protected static ?string $model = CampaignVehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    public static function form(Schema $schema): Schema
    {
        return CampaignVehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignVehiclesTable::configure($table);
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
            'index' => ListCampaignVehicles::route('/'),
            'create' => CreateCampaignVehicle::route('/create'),
            'edit' => EditCampaignVehicle::route('/{record}/edit'),
        ];
    }
}
