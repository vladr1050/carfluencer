<?php

namespace App\Filament\Resources\CampaignImpressionStats\Schemas;

use App\Models\CampaignImpressionStat;
use App\Services\ImpressionEngine\CampaignImpressionGeoZoneBreakdownService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class CampaignImpressionStatInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('campaign.name')
                    ->label('Campaign')
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state ?? '') {
                        CampaignImpressionStat::STATUS_QUEUED => 'gray',
                        CampaignImpressionStat::STATUS_PROCESSING => 'warning',
                        CampaignImpressionStat::STATUS_DONE => 'success',
                        CampaignImpressionStat::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('date_from')
                    ->date(),
                TextEntry::make('date_to')
                    ->date(),
                TextEntry::make('total_gross_impressions')
                    ->label('Snapshot total (gross)')
                    ->numeric(),
                TextEntry::make('mobility_data_version')
                    ->label('Mobility version'),
                TextEntry::make('coefficients_version')
                    ->label('Coefficients version'),
                TextEntry::make('calculation_version')
                    ->label('Engine version'),
                TextEntry::make('error_message')
                    ->label('Error')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->visible(fn (CampaignImpressionStat $record): bool => $record->status === CampaignImpressionStat::STATUS_FAILED),
                SchemaView::make('filament.impression-engine.snapshot-zone-breakdown')
                    ->key('snapshot_zone_breakdown')
                    ->viewData(function (?CampaignImpressionStat $record): array {
                        if ($record === null) {
                            return [
                                'breakdown' => [
                                    'available' => false,
                                    'reason' => 'Snapshot record is not available.',
                                    'note' => null,
                                    'total_impressions' => 0,
                                    'top_zones' => [],
                                    'unattributed_impressions' => 0,
                                    'unattributed_share_pct' => 0.0,
                                ],
                            ];
                        }

                        return [
                            'breakdown' => app(CampaignImpressionGeoZoneBreakdownService::class)->breakdownForSnapshot($record),
                        ];
                    })
                    ->columnSpanFull(),
            ]);
    }
}
