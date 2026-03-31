<?php

namespace App\Filament\Resources\CampaignImpressionStats;

use App\Filament\Resources\CampaignImpressionStats\Pages\ListCampaignImpressionStats;
use App\Filament\Resources\CampaignImpressionStats\Pages\ViewCampaignImpressionStat;
use App\Filament\Resources\CampaignImpressionStats\Schemas\CampaignImpressionStatForm;
use App\Filament\Resources\CampaignImpressionStats\Schemas\CampaignImpressionStatInfolist;
use App\Filament\Resources\CampaignImpressionStats\Tables\CampaignImpressionStatsTable;
use App\Models\CampaignImpressionStat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignImpressionStatResource extends Resource
{
    protected static ?string $model = CampaignImpressionStat::class;

    protected static ?string $navigationLabel = 'Impression snapshots';

    protected static ?string $modelLabel = 'Campaign impression snapshot';

    protected static ?string $pluralModelLabel = 'Campaign impression snapshots';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Impression Engine';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return CampaignImpressionStatForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampaignImpressionStatInfolist::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CampaignImpressionStatsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaignImpressionStats::route('/'),
            'view' => ViewCampaignImpressionStat::route('/{record}'),
        ];
    }
}
