<?php

namespace App\Filament\Resources\CampaignProofs;

use App\Filament\Resources\CampaignProofs\Pages\CreateCampaignProof;
use App\Filament\Resources\CampaignProofs\Pages\EditCampaignProof;
use App\Filament\Resources\CampaignProofs\Pages\ListCampaignProofs;
use App\Filament\Resources\CampaignProofs\Schemas\CampaignProofForm;
use App\Filament\Resources\CampaignProofs\Tables\CampaignProofsTable;
use App\Models\CampaignProof;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignProofResource extends Resource
{
    protected static ?string $model = CampaignProof::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    public static function form(Schema $schema): Schema
    {
        return CampaignProofForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignProofsTable::configure($table);
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
            'index' => ListCampaignProofs::route('/'),
            'create' => CreateCampaignProof::route('/create'),
            'edit' => EditCampaignProof::route('/{record}/edit'),
        ];
    }
}
