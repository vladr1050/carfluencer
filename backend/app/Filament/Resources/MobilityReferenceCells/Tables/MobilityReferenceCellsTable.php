<?php

namespace App\Filament\Resources\MobilityReferenceCells\Tables;

use App\Models\MobilityReferenceCell;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MobilityReferenceCellsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cell_id')->searchable()->limit(20),
                TextColumn::make('data_version')->sortable(),
                TextColumn::make('vehicle_aadt')->numeric()->sortable(),
                TextColumn::make('pedestrian_daily')->numeric()->sortable(),
                TextColumn::make('records_count')->numeric()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('data_version')
                    ->options(fn () => MobilityReferenceCell::query()
                        ->distinct()
                        ->orderBy('data_version')
                        ->pluck('data_version', 'data_version')
                        ->all()),
            ])
            ->defaultSort('id', 'desc');
    }
}
