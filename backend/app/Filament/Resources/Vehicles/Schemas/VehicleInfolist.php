<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('mediaOwner.name')
                    ->label('Media owner'),
                TextEntry::make('brand'),
                TextEntry::make('model'),
                TextEntry::make('year')
                    ->placeholder('-'),
                TextEntry::make('color')
                    ->placeholder('-'),
                TextEntry::make('quantity')
                    ->numeric(),
                ImageEntry::make('image_path')
                    ->placeholder('-'),
                TextEntry::make('imei'),
                Section::make(__('Telemetry (ClickHouse → PostgreSQL)'))
                    ->schema([
                        IconEntry::make('telemetry_pull_enabled')
                            ->label(__('Scheduled pull'))
                            ->boolean(),
                        TextEntry::make('telemetry_last_incremental_at')
                            ->label(__('Last incremental sync'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('telemetry_last_historical_at')
                            ->label(__('Last historical sync'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('telemetry_last_success_at')
                            ->label(__('Last successful pull'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('telemetry_last_error')
                            ->label(__('Last error'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                TextEntry::make('status'),
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
