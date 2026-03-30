<?php

namespace App\Filament\Resources\GeoZones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeoZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('min_lat')
                    ->label('S')
                    ->numeric(decimalPlaces: 5)
                    ->toggleable(),
                TextColumn::make('max_lat')
                    ->label('N')
                    ->numeric(decimalPlaces: 5)
                    ->toggleable(),
                TextColumn::make('min_lng')
                    ->label('W')
                    ->numeric(decimalPlaces: 5)
                    ->toggleable(),
                TextColumn::make('max_lng')
                    ->label('E')
                    ->numeric(decimalPlaces: 5)
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
