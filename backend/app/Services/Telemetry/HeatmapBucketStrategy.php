<?php

namespace App\Services\Telemetry;

use InvalidArgumentException;

/**
 * Central map-zoom → rollup tier → geographic rounding for heatmap_cells_daily and API reads.
 */
final class HeatmapBucketStrategy
{
    /**
     * @return list<array{max_zoom: int, decimals: int}>
     */
    public static function zoomTiers(): array
    {
        $tiers = config('telemetry.heatmap.rollup.zoom_tiers', []);
        if (! is_array($tiers) || $tiers === []) {
            return [
                ['max_zoom' => 22, 'decimals' => 3],
            ];
        }

        return array_values(array_map(function (mixed $row): array {
            if (! is_array($row)) {
                throw new InvalidArgumentException('telemetry.heatmap.rollup.zoom_tiers must be a list of {max_zoom, decimals}.');
            }

            return [
                'max_zoom' => max(1, min(22, (int) ($row['max_zoom'] ?? 22))),
                'decimals' => max(2, min(6, (int) ($row['decimals'] ?? 3))),
            ];
        }, $tiers));
    }

    public static function tierCount(): int
    {
        return count(self::zoomTiers());
    }

    /**
     * Leaflet map zoom (integer) → tier index [0, tierCount).
     */
    public static function tierFromMapZoom(int $mapZoom): int
    {
        $z = max(1, min(22, $mapZoom));
        $tiers = self::zoomTiers();
        foreach ($tiers as $idx => $row) {
            if ($z <= $row['max_zoom']) {
                return $idx;
            }
        }

        return max(0, count($tiers) - 1);
    }

    public static function decimalPlacesForTier(int $tier): int
    {
        $tiers = self::zoomTiers();
        $tier = max(0, min(count($tiers) - 1, $tier));

        return $tiers[$tier]['decimals'];
    }

    /**
     * PostgreSQL expression for bucketed latitude (must match aggregation INSERT).
     *
     * @param  string  $latColumn  SQL identifier, e.g. latitude
     */
    public static function pgsqlRoundLatExpr(string $latColumn, int $decimals): string
    {
        $decimals = max(2, min(6, $decimals));

        return 'ROUND(CAST('.$latColumn.' AS numeric), '.$decimals.')::double precision';
    }

    /**
     * @param  string  $lngColumn  SQL identifier, e.g. longitude
     */
    public static function pgsqlRoundLngExpr(string $lngColumn, int $decimals): string
    {
        $decimals = max(2, min(6, $decimals));

        return 'ROUND(CAST('.$lngColumn.' AS numeric), '.$decimals.')::double precision';
    }
}
