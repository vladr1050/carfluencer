<?php

namespace App\Services\Reports\HeatmapExport;

/**
 * Leaflet.heat options for campaign report parking export (PDF/PNG).
 *
 * @return array{
 *     radius: int,
 *     blur: int,
 *     maxZoom: int,
 *     minOpacity: float,
 *     max: float,
 *     gradient: array<string, string>
 * }
 */
final class ParkingHeatmapProfile
{
    public static function heatLayerOptions(int $radius, int $blur): array
    {
        /** @var array<string, mixed> $cfg */
        $cfg = config('reports.heatmaps.parking', []);
        /** @var array<string, string> $gradient */
        $gradient = is_array($cfg['gradient'] ?? null) ? $cfg['gradient'] : [];
        $minOpacity = (float) ($cfg['export_min_opacity'] ?? 0.38);
        $maxZoom = (int) ($cfg['max_zoom'] ?? 15);
        $maxHeat = (float) ($cfg['heat_max'] ?? 1.0);

        return [
            'radius' => max(4, $radius),
            'blur' => max(4, $blur),
            'maxZoom' => max(10, min(22, $maxZoom)),
            'minOpacity' => max(0.05, min(1.0, $minOpacity)),
            'max' => max(0.1, min(2.0, $maxHeat)),
            'gradient' => self::stringKeyGradient($gradient),
        ];
    }

    /**
     * @param  array<string, string>  $g
     * @return array<string, string>
     */
    private static function stringKeyGradient(array $g): array
    {
        $out = [];
        foreach ($g as $k => $v) {
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }
}
