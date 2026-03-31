<?php

namespace App\Filament\Resources\CampaignReports\Schemas;

use App\Models\CampaignImpressionStat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CampaignReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('campaign_id')
                    ->relationship('campaign', 'name')
                    ->live()
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('campaign_impression_stat_id')
                    ->label('Impressions snapshot ID')
                    ->helperText('Optional: if selected, PDF KPI "Impressions" will use snapshot total.')
                    ->options(function (Get $get): array {
                        $campaignId = $get('campaign_id');
                        if (! $campaignId) {
                            return [];
                        }

                        return CampaignImpressionStat::query()
                            ->where('campaign_id', $campaignId)
                            ->where('status', CampaignImpressionStat::STATUS_DONE)
                            ->orderByDesc('id')
                            ->limit(200)
                            ->get()
                            ->mapWithKeys(fn (CampaignImpressionStat $stat): array => [
                                (string) $stat->id => '#'.$stat->id.' · '.$stat->date_from->toDateString().' — '.$stat->date_to->toDateString(),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->nullable(),
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
