<?php

namespace App\Services\Reports;

/**
 * Filters heatmap points for PDF/PNG export (bad GPS, focus region).
 *
 * @phpstan-type Point array{lat: float, lng: float, intensity: float}
 */
final class ReportHeatmapExportPointFilter
{
    /**
     * @param  list<array<string, mixed>>  $points  Raw points from heatmap bundle (lat, lng, intensity).
     * @return list<array{lat: float, lng: float, intensity: float}>
     */
    public static function filter(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            if (! isset($p['lat'], $p['lng'], $p['intensity'])) {
                continue;
            }
            $lat = (float) $p['lat'];
            $lng = (float) $p['lng'];
            $intensity = (float) $p['intensity'];

            if (! self::isValidCoordinate($lat, $lng)) {
                continue;
            }

            if (config('reports.heatmap_export.clip_to_bounds') === true
                && ! self::withinBounds($lat, $lng)) {
                continue;
            }

            $out[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $intensity];
        }

        return $out;
    }

    private static function isValidCoordinate(float $lat, float $lng): bool
    {
        if (is_nan($lat) || is_nan($lng) || is_infinite($lat) || is_infinite($lng)) {
            return false;
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }
        // Типичный сбой: 0,0 (Африка / «пустые» координаты)
        if (abs($lat) < 1e-6 && abs($lng) < 1e-6) {
            return false;
        }

        return true;
    }

    private static function withinBounds(float $lat, float $lng): bool
    {
        /** @var array{south: float, north: float, west: float, east: float} $b */
        $b = config('reports.heatmap_export.bounds');

        return $lat >= $b['south'] && $lat <= $b['north']
            && $lng >= $b['west'] && $lng <= $b['east'];
    }
}
