<?php

namespace App\Services\Reports;

/**
 * Pick a hex color along export gradient stops (normalized t in [0,1]).
 *
 * @param  array<float|string, string>  $gradientStops
 */
final class ReportHeatmapGradientColor
{
    public static function at(float $t, array $gradientStops): string
    {
        $t = max(0.0, min(1.0, $t));
        $stops = [];
        foreach ($gradientStops as $k => $color) {
            $stops[(float) $k] = (string) $color;
        }
        ksort($stops, SORT_NUMERIC);
        $keys = array_keys($stops);
        if ($keys === []) {
            return '#888888';
        }
        $first = $keys[0];
        $last = $keys[count($keys) - 1];
        if ($t <= $first) {
            return $stops[$first];
        }
        if ($t >= $last) {
            return $stops[$last];
        }

        for ($i = 0; $i < count($keys) - 1; $i++) {
            $a = $keys[$i];
            $b = $keys[$i + 1];
            if ($t >= $a && $t <= $b) {
                $span = $b - $a;
                if ($span <= 0.0) {
                    return $stops[$b];
                }

                return self::lerpHex($stops[$a], $stops[$b], ($t - $a) / $span);
            }
        }

        return $stops[$last];
    }

    private static function lerpHex(string $from, string $to, float $u): string
    {
        $u = max(0.0, min(1.0, $u));
        $rf = self::parseHex($from);
        $rt = self::parseHex($to);
        $r = (int) round($rf[0] + ($rt[0] - $rf[0]) * $u);
        $g = (int) round($rf[1] + ($rt[1] - $rf[1]) * $u);
        $b = (int) round($rf[2] + ($rt[2] - $rf[2]) * $u);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function parseHex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
