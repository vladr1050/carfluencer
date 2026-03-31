<?php

namespace App\Filament\Resources\CampaignImpressionStats\Pages;

use App\Filament\Resources\CampaignImpressionStats\CampaignImpressionStatResource;
use App\Jobs\CalculateCampaignImpressionsJob;
use App\Models\Campaign;
use App\Models\CampaignImpressionStat;
use App\Models\ImpressionCoefficient;
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
                    Select::make('coefficients_version')
                        ->label('Coefficients version')
                        ->options(fn (): array => ImpressionCoefficient::query()
                            ->orderByDesc('id')
                            ->pluck('version', 'version')
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Select which coefficient setup to use for this run.'),
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

                    $campaign = Campaign::query()->findOrFail((int) $data['campaign_id']);

                    $queued = CampaignImpressionStat::query()->create([
                        'campaign_id' => $campaign->id,
                        'date_from' => (string) $from,
                        'date_to' => (string) $to,
                        'vehicles_count' => 0,
                        'driving_impressions' => 0,
                        'parking_impressions' => 0,
                        'total_gross_impressions' => 0,
                        'campaign_price' => (float) ($campaign->total_price ?? 0),
                        'cpm' => null,
                        'calculation_version' => (string) config('impression_engine.calculation.calculation_version', 'v1.0'),
                        'mobility_data_version' => (string) $data['mobility_data_version'],
                        'coefficients_version' => (string) $data['coefficients_version'],
                        'telemetry_sampling_seconds' => (int) config('impression_engine.calculation.telemetry_assumed_seconds_per_point', 10),
                        'input_fingerprint' => 'queued_'.bin2hex(random_bytes(16)),
                        'matched_direct_count' => 0,
                        'matched_fallback_count' => 0,
                        'unmatched_count' => 0,
                        'status' => CampaignImpressionStat::STATUS_QUEUED,
                        'error_message' => null,
                    ]);

                    CalculateCampaignImpressionsJob::dispatch(
                        (int) $data['campaign_id'],
                        (string) $from,
                        (string) $to,
                        (string) $data['mobility_data_version'],
                        (bool) ($data['force_recalculate'] ?? false),
                        (string) $data['coefficients_version'],
                        $queued->id,
                    );
                    Notification::make()
                        ->title("Calculation queued (#{$queued->id})")
                        ->success()
                        ->send();
                }),
        ];
    }
}
