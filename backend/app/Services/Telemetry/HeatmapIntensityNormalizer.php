<?php

namespace App\Services\Telemetry;

/**
 * Intensity 0…1 from raw bucket counts: absolute max or percentile cap + optional gamma.
 */
final class HeatmapIntensityNormalizer
{
    /**
     * @param  list<int>  $weights  Non-negative counts per bucket (same layer).
     */
    public static function capFromWeights(array $weights, string $mode): int
    {
        $weights = array_values(array_filter(array_map('intval', $weights), fn (int $w): bool => $w > 0));
        if ($weights === []) {
            return 1;
        }

        sort($weights);
        $n = count($weights);

        if ($mode === 'max') {
            return max(1, $weights[$n - 1]);
        }

        $p = $mode === 'p99' ? 99 : 95;
        $idx = (int) ceil($p / 100 * $n) - 1;

        return max(1, $weights[max(0, min($n - 1, $idx))]);
    }

    /**
     * ratio = min(1, w/cap); then apply gamma like {@see HeatmapBucketIntensity}.
     */
    public static function normalize(int $w, int $cap, float $gamma): float
    {
        $cap = max(1, $cap);
        $ratio = max(0.0, min(1.0, $w / $cap));
        if ($gamma <= 1.0) {
            return $ratio;
        }

        return min(1.0, $ratio ** $gamma);
    }

    /**
     * @param  list<int>  $weights
     */
    public static function rankPercentBelow(int $w, array $weights): float
    {
        if ($weights === []) {
            return 0.0;
        }
        $weights = array_map('intval', $weights);
        $n = count($weights);
        if ($n === 0) {
            return 0.0;
        }
        $below = 0;
        foreach ($weights as $v) {
            if ($v < $w) {
                $below++;
            }
        }

        return round(100.0 * $below / $n, 1);
    }
}
