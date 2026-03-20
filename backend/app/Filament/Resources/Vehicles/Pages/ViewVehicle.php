<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('telemetry_incremental')
                    ->label(__('New data (incremental)'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription(__('Queues a job to pull new points from ClickHouse for this IMEI.'))
                    ->action(function (): void {
                        SyncVehicleTelemetryFromClickHouseJob::dispatch($this->record->id, 'incremental', null, null);
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
                    ->action(function (array $data): void {
                        SyncVehicleTelemetryFromClickHouseJob::dispatch(
                            $this->record->id,
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
            EditAction::make(),
        ];
    }
}
