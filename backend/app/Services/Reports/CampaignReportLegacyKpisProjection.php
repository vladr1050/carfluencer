<?php

namespace App\Services\Reports;

use App\Services\Analytics\CampaignAnalyticsService;
use App\Services\Reports\Contracts\CampaignReportMetricsServiceInterface;

/**
 * Backward-compatible mirror of {@see CampaignReport::$report_data_json} key {@code kpis}.
 *
 * Consumers (exports, future UI) may still expect the legacy shape produced by
 * {@see CampaignReportMetricsServiceInterface::getKpis()}:
 * {@code driving_distance_km}, {@code driving_time_hours}, {@code parking_time_hours},
 * plus {@code data_source} and {@code is_estimated}.
 *
 * This projection copies values only from {@code analytics_snapshot} — no second telemetry
 * pass. Canonical metrics live under {@code analytics_snapshot.kpis} (new names:
 * {@code km_driven}, {@code driving_hours}, {@code parking_hours}).
 */
final class CampaignReportLegacyKpisProjection
{
    /**
     * @param  array<string, mixed>  $analyticsSnapshot  Output of {@see CampaignAnalyticsService::buildSnapshot()}
     * @return array{
     *     impressions: int,
     *     carfluencers: int,
     *     driving_distance_km: float,
     *     driving_time_hours: float,
     *     parking_time_hours: float,
     *     data_source: string,
     *     is_estimated: bool
     * }
     */
    public static function fromAnalyticsSnapshot(array $analyticsSnapshot): array
    {
        /** @var array<string, mixed> $k */
        $k = $analyticsSnapshot['kpis'] ?? [];
        /** @var array<string, mixed> $meta */
        $meta = $analyticsSnapshot['meta'] ?? [];

        $dataSource = $meta['data_source'] ?? null;
        if ($dataSource === null || $dataSource === '') {
            $dataSource = 'none';
        }

        return [
            'impressions' => (int) ($k['impressions'] ?? 0),
            'carfluencers' => (int) ($k['carfluencers'] ?? 0),
            'driving_distance_km' => round((float) ($k['km_driven'] ?? 0.0), 2),
            'driving_time_hours' => round((float) ($k['driving_hours'] ?? 0.0), 2),
            'parking_time_hours' => round((float) ($k['parking_hours'] ?? 0.0), 2),
            'data_source' => (string) $dataSource,
            'is_estimated' => (bool) ($meta['is_estimated'] ?? false),
        ];
    }
}
