<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Vehicle;
use App\Services\ImpressionEngine\CampaignImpressionCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    ) {}

    public function handle(CampaignImpressionCalculationService $calculation): void
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);

        $vehicleIds = Vehicle::query()
            ->join('campaign_vehicles', 'campaign_vehicles.vehicle_id', '=', 'vehicles.id')
            ->where('campaign_vehicles.campaign_id', $campaign->id)
            ->pluck('vehicles.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $calculation->calculate(
            $campaign,
            $this->dateFrom,
            $this->dateTo,
            $this->mobilityDataVersion,
            $this->forceRecalculate,
            $vehicleIds,
        );
    }
}
