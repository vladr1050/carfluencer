<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: which campaigns use this vehicle (admin sees full picture).
 */
class VehicleCampaignsRelationManager extends RelationManager
{
    protected static string $relationship = 'campaigns';

    protected static ?string $title = 'Campaign membership';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('advertiser.company_name')
                    ->label('Advertiser')
                    ->placeholder('—'),
                TextColumn::make('pivot.placement_size_class')
                    ->label('Placement'),
                TextColumn::make('pivot.status')
                    ->label('Link status')
                    ->badge(),
                TextColumn::make('start_date')->date()->placeholder('—'),
                TextColumn::make('end_date')->date()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }
}
