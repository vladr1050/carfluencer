<?php

namespace App\Services\ImpressionEngine\Geo;

final class HaversineMeters
{
    public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dP = deg2rad($lat2 - $lat1);
        $dL = deg2rad($lon2 - $lon1);

        $a = sin($dP / 2) ** 2 + cos($p1) * cos($p2) * sin($dL / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
