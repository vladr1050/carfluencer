<?php

namespace App\Services\Telemetry;

/**
 * Intensity 0…1 from raw bucket counts: absolute max or percentile cap + optional gamma (moving).
 * Stopped/parking uses a fixed mild power curve after the same cap (see {@see normalizeStopped}).
 */
final class HeatmapIntensityNormalizer
{
    /** Lifts mid-density vs linear; used only for stopped layer (Google-style density). */
    public const STOPPED_INTENSITY_POWER = 0.7;

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
     * Stopped layer: ratio = min(1, w/cap) with cap from p95/p99/max, then ratio to the power STOPPED_INTENSITY_POWER.
     * Exponent below 1 boosts mid-density so the map stays readable outside peak hubs.
     */
    public static function normalizeStopped(int $w, int $cap): float
    {
        $cap = max(1, $cap);
        $ratio = max(0.0, min(1.0, $w / $cap));

        return min(1.0, $ratio ** self::STOPPED_INTENSITY_POWER);
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

    /**
     * Same semantics as {@see rankPercentBelow} for each entry, in O(n log n) total (one sort + binary search per bucket).
     *
     * @param  list<int>  $weightsPerBucket
     * @return list<float>
     */
    public static function rankPercentBelowBatch(array $weightsPerBucket): array
    {
        $n = count($weightsPerBucket);
        if ($n === 0) {
            return [];
        }
        $sorted = array_map('intval', $weightsPerBucket);
        sort($sorted);
        $out = [];
        foreach ($weightsPerBucket as $w) {
            $w = (int) $w;
            $lo = 0;
            $hi = $n;
            while ($lo < $hi) {
                $mid = ($lo + $hi) >> 1;
                if ($sorted[$mid] < $w) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid;
                }
            }
            $out[] = round(100.0 * $lo / $n, 1);
        }

        return $out;
    }
}
