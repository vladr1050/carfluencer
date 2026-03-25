<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use Illuminate\Support\Collection;

/**
 * Single filter context for advertiser heatmap: map query uses all fields;
 * {@see HeatmapSummaryMetricsService} ignores bbox and zoom.
 */
final readonly class HeatmapPageQuery
{
    /**
     * @param  list<int>  $vehicleIdsFilter  Empty = all vehicles on the campaign.
     */
    public function __construct(
        public int $campaignId,
        public ?string $dateFrom,
        public ?string $dateTo,
        public array $vehicleIdsFilter,
        public string $mode,
        public string $normalization,
        public ?float $south,
        public ?float $west,
        public ?float $north,
        public ?float $east,
        public ?int $zoom,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  Same shape as legacy heatmap filters from the advertiser controller.
     */
    public static function fromAdvertiserFilters(int $campaignId, array $filters): self
    {
        $mode = $filters['mode'] ?? 'driving';
        if ($mode === 'both') {
            $mode = 'driving';
        }
        if (! in_array($mode, ['driving', 'parking'], true)) {
            $mode = 'driving';
        }

        $normalization = $filters['normalization'] ?? 'p95';
        if (! in_array($normalization, ['max', 'p95', 'p99'], true)) {
            $normalization = 'p95';
        }

        $vehicleIds = array_values(array_filter(array_map('intval', $filters['vehicle_ids'] ?? [])));

        return new self(
            campaignId: $campaignId,
            dateFrom: isset($filters['date_from']) ? (string) $filters['date_from'] : null,
            dateTo: isset($filters['date_to']) ? (string) $filters['date_to'] : null,
            vehicleIdsFilter: $vehicleIds,
            mode: $mode,
            normalization: $normalization,
            south: self::optionalFloat($filters['south'] ?? null),
            west: self::optionalFloat($filters['west'] ?? null),
            north: self::optionalFloat($filters['north'] ?? null),
            east: self::optionalFloat($filters['east'] ?? null),
            zoom: self::optionalInt($filters['zoom'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toFiltersArray(): array
    {
        return [
            'vehicle_ids' => $this->vehicleIdsFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'mode' => $this->mode,
            'normalization' => $this->normalization,
            'south' => $this->south,
            'west' => $this->west,
            'north' => $this->north,
            'east' => $this->east,
            'zoom' => $this->zoom,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    public function resolveCampaignVehicleIds(): Collection
    {
        $vehicleIds = Campaign::query()
            ->findOrFail($this->campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id');

        if ($this->vehicleIdsFilter !== []) {
            $allowed = $this->vehicleIdsFilter;
            $vehicleIds = $vehicleIds->filter(fn (int $id) => in_array($id, $allowed, true));
        }

        return $vehicleIds->values();
    }

    private static function optionalFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    private static function optionalInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }
}
