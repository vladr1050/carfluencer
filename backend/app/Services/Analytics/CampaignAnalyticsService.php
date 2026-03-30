<?php

namespace App\Services\Analytics;

use App\Models\Vehicle;
use App\Services\Reports\Contracts\CampaignReportMetricsServiceInterface;
use App\Services\Telemetry\HeatmapPageQuery;
use Carbon\CarbonImmutable;

/**
 * Canonical analytics snapshot for campaign PDF/report layers. KPIs reuse
 * {@see CampaignReportMetricsServiceInterface} (heatmap summary + advertiser trips KPI).
 *
 * Parking location narrative uses {@see CampaignParkingByZoneService} (GeoZones), not heatmap rollups.
 */
final class CampaignAnalyticsService
{
    private const SCHEMA_VERSION = 'v1';

    public function __construct(
        private readonly CampaignReportMetricsServiceInterface $reportMetrics,
        private readonly CampaignInsightsService $campaignInsightsService,
        private readonly CampaignCoverageService $campaignCoverageService,
        private readonly CampaignParkingByZoneService $campaignParkingByZoneService,
    ) {}

    /**
     * @param  list<int>  $vehicleIds  Empty = all vehicles on the campaign (same as advertiser heatmap).
     * @return array{
     *     kpis: array{
     *         impressions: int,
     *         carfluencers: int,
     *         km_driven: float,
     *         driving_hours: float,
     *         parking_hours: float,
     *         impressions_per_day: float,
     *         impressions_per_vehicle: float
     *     },
     *     exposure_split: array{driving_share: float, parking_share: float},
     *     top_locations: list<array<string, mixed>>  Always empty; retained for schema compatibility.
     *     time_distribution: array{day_vs_night: array{day: float, night: float, is_stub: bool}},
     *     meta: array{campaign_id: int, date_from: string, date_to: string, vehicle_ids: list<int>, schema_version: string, data_source: string, is_estimated: bool},
     *     coverage: array{unique_cells: int, reference_cells: int, coverage_ratio: float, coverage_pattern: string|null, coverage_narrative: string|null, method: string, denominator_scope: string, rollup_tier_index: int, map_zoom_used: int},
     *     insights: array{summary: string|null, highlights: list<string>, exposure_pattern: string|null, location_pattern: string|null},
     *     parking_by_zone: array<string, mixed>
     * }
     */
    public function buildSnapshot(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds = []
    ): array {
        $norm = (string) config('reports.normalization', 'max');
        if (! in_array($norm, ['max', 'p95', 'p99'], true)) {
            $norm = 'max';
        }

        $pageQuery = new HeatmapPageQuery(
            campaignId: $campaignId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            vehicleIdsFilter: array_values(array_map('intval', $vehicleIds)),
            mode: 'driving',
            normalization: $norm,
            south: null,
            west: null,
            north: null,
            east: null,
            zoom: null,
        );

        /** @var list<int> $resolvedVehicleIds */
        $resolvedVehicleIds = $pageQuery->resolveCampaignVehicleIds()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $kpisRaw = $this->reportMetrics->getKpis($campaignId, $dateFrom, $dateTo, $resolvedVehicleIds);

        $impressions = (int) ($kpisRaw['impressions'] ?? 0);
        $carfluencers = (int) ($kpisRaw['carfluencers'] ?? 0);
        $kmDriven = round((float) ($kpisRaw['driving_distance_km'] ?? 0.0), 2);
        $drivingHours = round((float) ($kpisRaw['driving_time_hours'] ?? 0.0), 2);
        $parkingHours = round((float) ($kpisRaw['parking_time_hours'] ?? 0.0), 2);

        $calendarDays = $this->inclusiveCalendarDays($dateFrom, $dateTo);
        $vehicleCount = count($resolvedVehicleIds);
        $impressionsPerDay = $calendarDays > 0
            ? round($impressions / $calendarDays, 2)
            : 0.0;
        $impressionsPerVehicle = $vehicleCount > 0
            ? round($impressions / $vehicleCount, 2)
            : 0.0;

        $exposure = $this->exposureSplit($drivingHours, $parkingHours);

        $imeis = Vehicle::query()
            ->whereIn('id', $resolvedVehicleIds)
            ->pluck('imei')
            ->filter()
            ->map(fn ($i) => (string) $i)
            ->values()
            ->all();

        $parkingByZone = $this->campaignParkingByZoneService->build(
            $campaignId,
            $dateFrom,
            $dateTo,
            $vehicleIds
        );

        $coverageMapZoom = (int) config('reports.coverage.map_zoom', 12);
        $coverage = $this->campaignCoverageService->buildCoverage(
            $dateFrom,
            $dateTo,
            $imeis,
            $coverageMapZoom
        );
        $coverage['coverage_narrative'] = CampaignCoverageNarrative::describe(
            $coverage['coverage_pattern'] ?? null,
            $parkingByZone
        );

        $snapshot = [
            'kpis' => [
                'impressions' => $impressions,
                'carfluencers' => $carfluencers,
                'km_driven' => $kmDriven,
                'driving_hours' => $drivingHours,
                'parking_hours' => $parkingHours,
                'impressions_per_day' => $impressionsPerDay,
                'impressions_per_vehicle' => $impressionsPerVehicle,
            ],
            'exposure_split' => $exposure,
            'top_locations' => [],
            'time_distribution' => [
                'day_vs_night' => [
                    // TODO: replace with hourly analytics when an hourly aggregate layer exists (heatmap_cells_daily has no hour dimension).
                    'day' => 0.75,
                    'night' => 0.25,
                    'is_stub' => true,
                ],
            ],
            'meta' => [
                'campaign_id' => $campaignId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'vehicle_ids' => $resolvedVehicleIds,
                'schema_version' => self::SCHEMA_VERSION,
                'data_source' => (string) ($kpisRaw['data_source'] ?? 'none'),
                'is_estimated' => (bool) ($kpisRaw['is_estimated'] ?? false),
            ],
            'coverage' => $coverage,
            'parking_by_zone' => $parkingByZone,
        ];

        $snapshot['insights'] = $this->campaignInsightsService->buildInsights($snapshot);

        return $snapshot;
    }

    /**
     * @return array{driving_share: float, parking_share: float}
     */
    private function exposureSplit(float $drivingHours, float $parkingHours): array
    {
        $total = $drivingHours + $parkingHours;
        if ($total <= 0.0) {
            return [
                'driving_share' => 0.0,
                'parking_share' => 0.0,
            ];
        }

        return [
            'driving_share' => round($drivingHours / $total, 4),
            'parking_share' => round($parkingHours / $total, 4),
        ];
    }

    private function inclusiveCalendarDays(string $dateFrom, string $dateTo): int
    {
        $start = CarbonImmutable::parse($dateFrom)->startOfDay();
        $end = CarbonImmutable::parse($dateTo)->startOfDay();
        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
