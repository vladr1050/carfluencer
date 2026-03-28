<?php

namespace App\Services\Analytics;

/**
 * Counts how many discrete bucket coordinates (step = 10^-decimals) fall inside a bbox.
 * Matches heatmap_cells_daily bucket granularity for a given rollup tier.
 */
final class CoverageReferenceGrid
{
    /**
     * Number of (lat_bucket, lng_bucket) pairs with values m/scale, n/scale that lie in the closed bbox.
     */
    public static function referenceCellCountInBBox(
        float $south,
        float $north,
        float $west,
        float $east,
        int $decimals
    ): int {
        if ($south >= $north || $west >= $east) {
            return 0;
        }

        $decimals = max(2, min(6, $decimals));
        $scale = (int) (10 ** $decimals);

        $latSpan = self::indexSpanInclusive($south, $north, $scale);
        if ($latSpan === null) {
            return 0;
        }

        $lngSpan = self::indexSpanInclusive($west, $east, $scale);
        if ($lngSpan === null) {
            return 0;
        }

        return $latSpan * $lngSpan;
    }

    /**
     * @return int|null  null if span empty
     */
    private static function indexSpanInclusive(float $lo, float $hi, int $scale): ?int
    {
        $minIdx = (int) ceil($lo * $scale - 1e-9);
        $maxIdx = (int) floor($hi * $scale + 1e-9);
        if ($maxIdx < $minIdx) {
            return null;
        }

        return $maxIdx - $minIdx + 1;
    }
}
