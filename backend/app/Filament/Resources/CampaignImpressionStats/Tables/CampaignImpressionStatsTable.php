<?php

namespace App\Filament\Resources\CampaignImpressionStats\Tables;

use App\Filament\Resources\CampaignImpressionStats\CampaignImpressionStatResource;
use App\Models\CampaignImpressionStat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignImpressionStatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('campaign.name')->label('Campaign')->searchable(),
                TextColumn::make('date_from')->date()->sortable(),
                TextColumn::make('date_to')->date()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CampaignImpressionStat::STATUS_QUEUED => 'gray',
                        CampaignImpressionStat::STATUS_PROCESSING => 'warning',
                        CampaignImpressionStat::STATUS_DONE => 'success',
                        CampaignImpressionStat::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_gross_impressions')->numeric()->sortable(),
                TextColumn::make('driving_impressions')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parking_impressions')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpm')->numeric(decimalPlaces: 4)->sortable(),
                TextColumn::make('mobility_data_version')->limit(24)->toggleable(),
                TextColumn::make('coefficients_version')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Last error')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->contentFooter(view('filament.impression-engine.impression-snapshots-explainer'))
            ->recordUrl(fn (CampaignImpressionStat $record): string => CampaignImpressionStatResource::getUrl('view', ['record' => $record]))
            ->defaultSort('id', 'desc');
    }
}
