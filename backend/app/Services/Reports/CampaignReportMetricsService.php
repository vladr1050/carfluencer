<?php

namespace App\Services\Reports;

use App\Models\Trip;
use App\Services\Reports\Contracts\CampaignReportMetricsServiceInterface;
use App\Services\Telemetry\HeatmapPageQuery;
use App\Services\Telemetry\HeatmapRequestDateRange;
use App\Services\Telemetry\HeatmapSummaryMetricsService;
use Illuminate\Support\Carbon;

final class CampaignReportMetricsService implements CampaignReportMetricsServiceInterface
{
    public function __construct(
        private readonly HeatmapSummaryMetricsService $heatmapSummary,
    ) {}

    public function getKpis(int $campaignId, string $dateFrom, string $dateTo, array $vehicleIds): array
    {
        HeatmapRequestDateRange::assertWithinConfiguredLimit($dateFrom, $dateTo);

        $norm = (string) config('reports.normalization', 'max');
        if (! in_array($norm, ['max', 'p95', 'p99'], true)) {
            $norm = 'max';
        }

        $query = new HeatmapPageQuery(
            campaignId: $campaignId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            vehicleIdsFilter: $vehicleIds,
            mode: 'driving',
            normalization: $norm,
            south: null,
            west: null,
            north: null,
            east: null,
            zoom: null,
        );

        $summary = $this->heatmapSummary->fetchForAdvertiser($query);
        $carfluencers = $this->countCarfluencers($vehicleIds, $dateFrom, $dateTo);

        return [
            'impressions' => $summary['impressions'],
            'driving_distance_km' => $summary['driving_distance_km'],
            'driving_time_hours' => $summary['driving_time_hours'],
            'parking_time_hours' => $summary['parking_time_hours'],
            'carfluencers' => $carfluencers,
            'data_source' => $summary['data_source'],
            'is_estimated' => (bool) $summary['is_estimated'],
        ];
    }

    /**
     * @param  list<int>  $vehicleIds
     */
    private function countCarfluencers(array $vehicleIds, string $dateFrom, string $dateTo): int
    {
        if ($vehicleIds === []) {
            return 0;
        }

        return Trip::query()
            ->where('trip_status', Trip::STATUS_COMPLETED)
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('trip_end')
            ->whereBetween('trip_end', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ])
            ->count();
    }
}
