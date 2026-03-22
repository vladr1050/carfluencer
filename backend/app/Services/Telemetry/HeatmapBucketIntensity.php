<?php

namespace App\Services\Telemetry;

/**
 * Normalizes bucket sample count (w) vs max bucket (maxW) into 0..1 for leaflet.heat.
 *
 * Gamma > 1 darkens mid-density cells so the highest-concentration buckets stand out more.
 * Gamma = 1 is linear (legacy): w/maxW.
 */
final class HeatmapBucketIntensity
{
    public static function normalize(float|int $w, int $maxW): float
    {
        $maxW = max(1, $maxW);
        $ratio = max(0.0, min(1.0, (float) $w / $maxW));
        $gamma = self::gamma();
        if ($gamma <= 1.0) {
            return $ratio;
        }

        return min(1.0, $ratio ** $gamma);
    }

    private static function gamma(): float
    {
        return TelemetryHeatmapConfig::intensityGamma();
    }
}
