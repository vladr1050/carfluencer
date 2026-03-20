<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Jobs\SyncTelemetryScopeFromClickHouseJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('telemetry_incremental_campaign')
                ->label(__('Telemetry: all campaign vehicles (incremental)'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('Queues a job to pull new points from ClickHouse for every vehicle linked to this campaign (campaign_vehicles). Same options: Telematics → ClickHouse & automation.'))
                ->action(function (): void {
                    SyncTelemetryScopeFromClickHouseJob::dispatch(
                        'incremental',
                        'campaign',
                        $this->getRecord()->getKey(),
                    );
                    Notification::make()->title(__('Telemetry sync queued'))->success()->send();
                }),
            Action::make('telemetry_historical_campaign')
                ->label(__('Telemetry: all campaign vehicles (historical)'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->modalHeading(__('Historical load for all campaign vehicles'))
                ->modalDescription(__('Uses the same scoped jobs as Telematics → ClickHouse & automation, limited to this campaign’s vehicles.'))
                ->form([
                    DatePicker::make('date_from')->label(__('From'))->required()->native(false),
                    DatePicker::make('date_to')->label(__('To'))->required()->native(false)->afterOrEqual('date_from'),
                ])
                ->action(function (array $data): void {
                    SyncTelemetryScopeFromClickHouseJob::dispatch(
                        'historical',
                        'campaign',
                        $this->getRecord()->getKey(),
                        [],
                        $data['date_from'],
                        $data['date_to'],
                    );
                    Notification::make()->title(__('Historical telemetry sync queued'))->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
