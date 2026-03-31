<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignImpressionStat;
use App\Models\Vehicle;
use App\Services\ImpressionEngine\CampaignImpressionCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CalculateCampaignImpressionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $campaignId,
        public string $dateFrom,
        public string $dateTo,
        public string $mobilityDataVersion,
        public bool $forceRecalculate = false,
        public ?string $coefficientsVersion = null,
        public ?int $snapshotId = null,
    ) {}

    public function handle(CampaignImpressionCalculationService $calculation): void
    {
        $snapshot = $this->snapshotId !== null
            ? CampaignImpressionStat::query()->find($this->snapshotId)
            : null;

        if ($snapshot !== null) {
            $snapshot->update([
                'status' => CampaignImpressionStat::STATUS_PROCESSING,
                'error_message' => null,
            ]);
        }

        $campaign = Campaign::query()->findOrFail($this->campaignId);

        $vehicleIds = Vehicle::query()
            ->join('campaign_vehicles', 'campaign_vehicles.vehicle_id', '=', 'vehicles.id')
            ->where('campaign_vehicles.campaign_id', $campaign->id)
            ->pluck('vehicles.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        try {
            $calculation->calculate(
                $campaign,
                $this->dateFrom,
                $this->dateTo,
                $this->mobilityDataVersion,
                $this->forceRecalculate,
                $vehicleIds,
                $this->coefficientsVersion,
                $snapshot,
            );
        } catch (Throwable $e) {
            if ($snapshot !== null) {
                $snapshot->update([
                    'status' => CampaignImpressionStat::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 2000),
                ]);
            }

            throw $e;
        }
    }
}
