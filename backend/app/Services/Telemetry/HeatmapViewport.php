<?php

namespace App\Services\Telemetry;

/**
 * Validates Leaflet bounds + zoom from HTTP query parameters.
 */
final class HeatmapViewport
{
    /**
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float, zoom: int}|null
     */
    public static function parse(array $filters): ?array
    {
        foreach (['south', 'west', 'north', 'east', 'zoom'] as $k) {
            if (! array_key_exists($k, $filters) || $filters[$k] === null || $filters[$k] === '') {
                return null;
            }
        }

        $south = (float) $filters['south'];
        $west = (float) $filters['west'];
        $north = (float) $filters['north'];
        $east = (float) $filters['east'];
        $zoom = (int) $filters['zoom'];

        if ($zoom < 1 || $zoom > 22) {
            return null;
        }

        if ($south < -90 || $north > 90 || $south >= $north) {
            return null;
        }

        if ($west < -180 || $east > 180 || $west >= $east) {
            return null;
        }

        return [
            'min_lat' => $south,
            'max_lat' => $north,
            'min_lng' => $west,
            'max_lng' => $east,
            'zoom' => $zoom,
        ];
    }

    public static function shouldReadRollup(array $filters): bool
    {
        if (! filter_var(config('telemetry.heatmap.rollup.read_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return self::parse($filters) !== null;
    }

    public static function legacyFallbackAllowed(): bool
    {
        return filter_var(config('telemetry.heatmap.rollup.legacy_fallback_without_viewport', true), FILTER_VALIDATE_BOOLEAN);
    }
}
