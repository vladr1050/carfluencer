<?php

namespace App\Filament\Resources\CampaignReports;

use App\Filament\Resources\CampaignReports\Pages\CreateCampaignReport;
use App\Filament\Resources\CampaignReports\Pages\ListCampaignReports;
use App\Filament\Resources\CampaignReports\Schemas\CampaignReportForm;
use App\Filament\Resources\CampaignReports\Tables\CampaignReportsTable;
use App\Models\CampaignReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CampaignReportResource extends Resource
{
    protected static ?string $model = CampaignReport::class;

    protected static ?string $navigationLabel = 'Campaign reports';

    protected static ?int $navigationSort = 25;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Campaigns';

    public static function form(Schema $schema): Schema
    {
        return CampaignReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaignReports::route('/'),
            'create' => CreateCampaignReport::route('/create'),
        ];
    }
}
