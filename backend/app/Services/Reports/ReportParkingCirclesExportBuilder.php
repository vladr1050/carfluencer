<?php

namespace App\Services\Reports;

use Illuminate\Support\Str;

/**
 * Builds circle marker payloads for parking report PNG export from analytics_snapshot.top_locations.
 *
 * Viewport policy (report export):
 * - fit_to_data viewports: use all top_locations; map bounds = circles on this page.
 * - fixed bbox viewports: only locations inside that bbox; bounds = those circles (or fallback if none).
 */
final class ReportParkingCirclesExportBuilder
{
    /**
     * @param  list<array<string, mixed>>  $topLocations  analytics_snapshot.top_locations
     * @param  array<string, mixed>  $viewport  {@see ReportHeatmapViewports}
     * @return list<array{lat: float, lng: float, radius_px: float, fillColor: string, fillOpacity: float, weight: int, color: string, label: int|null, tooltip: string}>
     */
    public static function build(array $topLocations, array $viewport): array
    {
        $cfg = config('reports.heatmaps.parking', []);
        $radiusScale = (float) ($cfg['radius_scale'] ?? 1.2);
        $minRadius = (float) ($cfg['min_radius'] ?? 8.0);
        /** @var array<float|string, string> $gradient */
        $gradient = is_array($cfg['gradient'] ?? null) ? $cfg['gradient'] : [];

        $filtered = self::filterByViewport($topLocations, $viewport);
        if ($filtered === []) {
            return [];
        }

        $maxDwell = 0.0;
        foreach ($filtered as $loc) {
            $d = (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0);
            if ($d > $maxDwell) {
                $maxDwell = $d;
            }
        }
        if ($maxDwell <= 0.0) {
            $maxDwell = 1.0;
        }

        $out = [];
        foreach (array_values($filtered) as $idx => $loc) {
            $lat = (float) ($loc['lat'] ?? 0);
            $lng = (float) ($loc['lng'] ?? 0);
            $dwell = (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0);
            $n = max(0.0, min(1.0, $dwell / $maxDwell));
            $radiusPx = max($minRadius, sqrt(max(0.0, $dwell)) * $radiusScale);
            $fillColor = $gradient !== [] ? ReportHeatmapGradientColor::at($n, $gradient) : '#8E24AA';
            $label = $idx < 5 ? $idx + 1 : null;
            $human = $loc['label'] ?? null;
            $tooltip = 'Parking intensity: '.(int) round($dwell);
            if (is_string($human) && trim($human) !== '') {
                $tooltip .= ' · '.Str::limit(trim($human), 72);
            }
            $out[] = [
                'lat' => $lat,
                'lng' => $lng,
                'radius_px' => round($radiusPx, 2),
                'fillColor' => $fillColor,
                'fillOpacity' => 0.72,
                'weight' => 2,
                'color' => '#333333',
                'label' => $label,
                'tooltip' => $tooltip,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     * @return list<array<string, mixed>>
     */
    private static function filterByViewport(array $topLocations, array $viewport): array
    {
        $fit = filter_var($viewport['fit_to_data'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($fit) {
            return array_values($topLocations);
        }

        $south = isset($viewport['south']) ? (float) $viewport['south'] : null;
        $north = isset($viewport['north']) ? (float) $viewport['north'] : null;
        $west = isset($viewport['west']) ? (float) $viewport['west'] : null;
        $east = isset($viewport['east']) ? (float) $viewport['east'] : null;
        if ($south === null || $north === null || $west === null || $east === null) {
            return array_values($topLocations);
        }

        $out = [];
        foreach ($topLocations as $loc) {
            $lat = (float) ($loc['lat'] ?? 0);
            $lng = (float) ($loc['lng'] ?? 0);
            if ($lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east) {
                $out[] = $loc;
            }
        }

        return $out;
    }
}
