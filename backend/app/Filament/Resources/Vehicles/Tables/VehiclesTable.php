<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Jobs\SyncTelemetryScopeFromClickHouseJob;
use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mediaOwner.name')
                    ->searchable(),
                TextColumn::make('brand')
                    ->searchable(),
                TextColumn::make('model')
                    ->searchable(),
                TextColumn::make('year')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('color')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                ImageColumn::make('image_path'),
                TextColumn::make('imei')
                    ->searchable(),
                IconColumn::make('telemetry_pull_enabled')
                    ->label(__('Sched. pull'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('telemetry_last_success_at')
                    ->label(__('Last CH sync'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('telemetry_last_error')
                    ->label(__('Sync error'))
                    ->limit(40)
                    ->tooltip(fn ($state): ?string => is_string($state) ? $state : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('telemetry_pull_enabled')
                    ->label(__('Scheduled ClickHouse pull'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Enabled'))
                    ->falseLabel(__('Disabled')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('telemetry_incremental')
                        ->label(__('New data (incremental)'))
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->modalDescription(__('Queues a job to pull new points from ClickHouse for this IMEI (per-device cursor).'))
                        ->action(function (\App\Models\Vehicle $record): void {
                            SyncVehicleTelemetryFromClickHouseJob::dispatch($record->id, 'incremental', null, null);
                            Notification::make()->title(__('Telemetry sync queued'))->success()->send();
                        }),
                    Action::make('telemetry_historical')
                        ->label(__('Backfill (date range)'))
                        ->icon('heroicon-o-clock')
                        ->modalHeading(__('Historical load (ClickHouse → PostgreSQL)'))
                        ->form([
                            DatePicker::make('date_from')->label(__('From'))->required()->native(false),
                            DatePicker::make('date_to')->label(__('To'))->required()->native(false)->afterOrEqual('date_from'),
                        ])
                        ->action(function (\App\Models\Vehicle $record, array $data): void {
                            SyncVehicleTelemetryFromClickHouseJob::dispatch(
                                $record->id,
                                'historical',
                                $data['date_from'],
                                $data['date_to']
                            );
                            Notification::make()->title(__('Historical telemetry sync queued'))->success()->send();
                        }),
                ])
                    ->label(__('ClickHouse'))
                    ->icon(Heroicon::OutlinedSignal)
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('telemetry_clickhouse')
                        ->label(__('ClickHouse sync'))
                        ->icon(Heroicon::OutlinedSignal)
                        ->color('gray')
                        ->modalHeading(__('Sync selected vehicles from ClickHouse'))
                        ->modalDescription(__('Same jobs as Telematics → ClickHouse & automation, scoped to the rows you selected.'))
                        ->form([
                            ToggleButtons::make('sync_kind')
                                ->label(__('What to pull'))
                                ->options([
                                    'incremental' => __('New data only'),
                                    'historical' => __('Backfill date range'),
                                ])
                                ->inline()
                                ->required()
                                ->live(),
                            DatePicker::make('date_from')
                                ->label(__('From'))
                                ->native(false)
                                ->visible(fn (Get $get): bool => $get('sync_kind') === 'historical')
                                ->required(fn (Get $get): bool => $get('sync_kind') === 'historical'),
                            DatePicker::make('date_to')
                                ->label(__('To'))
                                ->native(false)
                                ->visible(fn (Get $get): bool => $get('sync_kind') === 'historical')
                                ->required(fn (Get $get): bool => $get('sync_kind') === 'historical')
                                ->afterOrEqual('date_from'),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $ids = $records->modelKeys();
                            if (($data['sync_kind'] ?? '') === 'historical') {
                                if (empty($data['date_from']) || empty($data['date_to'])) {
                                    Notification::make()->title(__('Pick a date range'))->danger()->send();

                                    return;
                                }
                                SyncTelemetryScopeFromClickHouseJob::dispatch(
                                    'historical',
                                    'vehicles',
                                    null,
                                    $ids,
                                    $data['date_from'],
                                    $data['date_to'],
                                );
                                Notification::make()->title(__('Historical telemetry sync queued'))->success()->send();

                                return;
                            }

                            SyncTelemetryScopeFromClickHouseJob::dispatch(
                                'incremental',
                                'vehicles',
                                null,
                                $ids,
                            );
                            Notification::make()->title(__('Telemetry sync queued'))->success()->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
