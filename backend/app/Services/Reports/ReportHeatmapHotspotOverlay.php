<?php

namespace App\Services\Reports;

/**
 * Top zones drawn on static report maps (labels only; heat shows structure).
 */
final class ReportHeatmapHotspotOverlay
{
    /**
     * @param  list<array{lat: float, lng: float, w: int}>  $topByWeight  highest-weight cells (e.g. first rows of a desc sort)
     * @param  list<array<string, mixed>>|null  $parkingTopLocations  analytics_snapshot.top_locations
     * @param  array<string, mixed>  $viewport
     * @return list<array{lat: float, lng: float, title: string, subtitle: string}>
     */
    public static function build(string $mode, array $topByWeight, ?array $parkingTopLocations, array $viewport): array
    {
        if ($mode === 'parking') {
            return self::buildParking($parkingTopLocations ?? [], $topByWeight, $viewport);
        }

        return self::buildDriving($topByWeight);
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     * @param  list<array{lat: float, lng: float, w: int}>  $fallbackCells
     * @return list<array{lat: float, lng: float, title: string, subtitle: string}>
     */
    private static function buildParking(array $topLocations, array $fallbackCells, array $viewport): array
    {
        $filtered = self::filterTopLocationsByViewport($topLocations, $viewport);
        $maxD = 0.0;
        foreach ($filtered as $loc) {
            $maxD = max($maxD, (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0));
        }
        if ($maxD <= 0.0) {
            $maxD = 1.0;
        }

        $out = [];
        foreach (array_slice(array_values($filtered), 0, 5) as $i => $loc) {
            $lat = (float) ($loc['lat'] ?? 0);
            $lng = (float) ($loc['lng'] ?? 0);
            $d = (float) ($loc['dwell_proxy'] ?? $loc['samples'] ?? 0);
            $pct = (int) round(100 * $d / $maxD);
            $label = $loc['label'] ?? null;
            $title = is_string($label) && trim($label) !== ''
                ? trim($label)
                : sprintf('%.2f, %.2f', $lat, $lng);
            $out[] = [
                'lat' => $lat,
                'lng' => $lng,
                'title' => $title,
                'subtitle' => 'Relative dwell (rollup): '.$pct.'%',
            ];
        }

        if ($out !== []) {
            return $out;
        }

        return self::buildDriving(array_slice($fallbackCells, 0, 5));
    }

    /**
     * @param  list<array{lat: float, lng: float, w: int}>  $topByWeight
     * @return list<array{lat: float, lng: float, title: string, subtitle: string}>
     */
    private static function buildDriving(array $topByWeight): array
    {
        $maxW = $topByWeight[0]['w'] ?? 0;
        if ($maxW <= 0) {
            $maxW = 1;
        }

        $out = [];
        foreach (array_values($topByWeight) as $i => $c) {
            if ($i >= 5) {
                break;
            }
            $w = (int) ($c['w'] ?? 0);
            $pct = (int) round(100 * $w / $maxW);
            $out[] = [
                'lat' => (float) $c['lat'],
                'lng' => (float) $c['lng'],
                'title' => 'Hotspot '.($i + 1),
                'subtitle' => '≈ '.$pct.'% vs peak activity (rollup)',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     * @return list<array<string, mixed>>
     */
    private static function filterTopLocationsByViewport(array $topLocations, array $viewport): array
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
