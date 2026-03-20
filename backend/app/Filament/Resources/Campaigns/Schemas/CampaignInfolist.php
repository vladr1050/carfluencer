<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Models\Campaign;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('advertiser.name')
                    ->label('Advertiser'),
                TextEntry::make('name'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('vehicles_count')
                    ->label(__('Linked vehicles (telemetry)'))
                    ->counts('vehicles')
                    ->formatStateUsing(function ($state): string {
                        $n = (int) $state;

                        return __(':count linked vehicle(s). Campaign-level telemetry sync uses every vehicle on “Campaign vehicles”.', ['count' => $n]);
                    })
                    ->columnSpanFull(),
                TextEntry::make('telemetry_linked_placeholder')
                    ->label(__('Telemetry sync (linked vehicles)'))
                    ->state(fn (Campaign $record): string => $record->telemetryLinkedVehiclesSummaryLine())
                    ->columnSpanFull(),
                TextEntry::make('status'),
                TextEntry::make('start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('end_date')
                    ->date()
                    ->placeholder('-'),
                IconEntry::make('created_by_admin')
                    ->boolean(),
                TextEntry::make('created_by_user_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('discount_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('platform_commission_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('agency_commission_percent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('total_price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
