<?php

namespace App\Filament\Resources\CampaignReports\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CampaignReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('date_from')
                    ->required()
                    ->native(false),
                DatePicker::make('date_to')
                    ->required()
                    ->native(false)
                    ->afterOrEqual('date_from'),
            ]);
    }
}
