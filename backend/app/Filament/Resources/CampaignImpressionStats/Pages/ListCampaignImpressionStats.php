<?php

namespace App\Filament\Resources\CampaignImpressionStats\Pages;

use App\Filament\Resources\CampaignImpressionStats\CampaignImpressionStatResource;
use App\Jobs\CalculateCampaignImpressionsJob;
use App\Models\Campaign;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCampaignImpressionStats extends ListRecords
{
    protected static string $resource = CampaignImpressionStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queueCalculation')
                ->label('Queue calculation')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaign')
                        ->options(fn (): array => Campaign::query()
                            ->orderByDesc('id')
                            ->limit(500)
                            ->get()
                            ->mapWithKeys(fn (Campaign $c): array => [$c->id => "#{$c->id} {$c->name}"])
                            ->all())
                        ->searchable()
                        ->required(),
                    DatePicker::make('date_from')
                        ->required()
                        ->native(false),
                    DatePicker::make('date_to')
                        ->required()
                        ->native(false)
                        ->afterOrEqual('date_from'),
                    TextInput::make('mobility_data_version')
                        ->default('riga_v1_2025')
                        ->required()
                        ->maxLength(64),
                    Toggle::make('force_recalculate')
                        ->label('Force recalculate (replaces snapshot with same inputs)')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $from = $data['date_from'];
                    $to = $data['date_to'];
                    if ($from instanceof DateTimeInterface) {
                        $from = $from->format('Y-m-d');
                    }
                    if ($to instanceof DateTimeInterface) {
                        $to = $to->format('Y-m-d');
                    }

                    CalculateCampaignImpressionsJob::dispatch(
                        (int) $data['campaign_id'],
                        (string) $from,
                        (string) $to,
                        (string) $data['mobility_data_version'],
                        (bool) ($data['force_recalculate'] ?? false),
                    );
                    Notification::make()
                        ->title('Calculation job queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
