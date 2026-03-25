<?php

namespace App\Services\Telemetry;

class DatabaseHeatmapDataService implements HeatmapDataServiceInterface
{
    public function __construct(
        private readonly HeatmapMapQueryService $mapQuery,
        private readonly HeatmapSummaryMetricsService $summaryMetrics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     map: array<string, mixed>,
     *     debug: array<string, mixed>,
     *     summary_metrics: array<string, mixed>
     * }
     */
    public function fetchHeatmapData(int $campaignId, array $filters = []): array
    {
        $query = HeatmapPageQuery::fromAdvertiserFilters($campaignId, $filters);
        $mapBlock = $this->mapQuery->fetchMapAndDebug($query);

        return [
            'map' => $mapBlock['map'],
            'debug' => $mapBlock['debug'],
            'summary_metrics' => $this->summaryMetrics->fetchForAdvertiser($query),
        ];
    }
}
