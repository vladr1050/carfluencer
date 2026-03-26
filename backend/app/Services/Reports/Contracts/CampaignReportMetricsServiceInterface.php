<?php

namespace App\Services\Reports\Contracts;

/**
 * Single entry point for campaign PDF KPIs (telemetry via heatmap summary + trips for Carfluencers).
 *
 * @phpstan-type ReportKpis array{
 *     impressions: int|float|null,
 *     driving_distance_km: float|null,
 *     driving_time_hours: float|null,
 *     parking_time_hours: float|null,
 *     carfluencers: int,
 *     data_source: string,
 *     is_estimated: bool
 * }
 */
interface CampaignReportMetricsServiceInterface
{
    /**
     * @param  list<int>  $vehicleIds  Resolved once for the report; same set for all metrics.
     * @return ReportKpis
     */
    public function getKpis(int $campaignId, string $dateFrom, string $dateTo, array $vehicleIds): array;
}
