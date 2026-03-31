<?php

namespace App\Services\ImpressionEngine;

use App\Models\ImpressionCoefficient;

final class ImpressionFormula
{
    /**
     * @param  array<string, mixed>  $mobility  vehicle_aadt, pedestrian_daily, hourly_peak_factor
     */
    public static function hourlyFlows(array $mobility, int $hourLocal): array
    {
        $v = (int) $mobility['vehicle_aadt'] / 24.0;
        $p = (int) $mobility['pedestrian_daily'] / 24.0;
        if (ImpressionPeakHour::isPeakHour($hourLocal)) {
            $peak = (float) $mobility['hourly_peak_factor'];
            $v *= $peak;
            $p *= $peak;
        }

        return ['vehicle_hourly' => $v, 'pedestrian_hourly' => $p];
    }

    public static function speedModifier(float $avgSpeedKmh, ImpressionCoefficient $c): float
    {
        if ($avgSpeedKmh < 30) {
            return (float) $c->speed_factor_low;
        }
        if ($avgSpeedKmh < 50) {
            return (float) $c->speed_factor_medium;
        }
        if ($avgSpeedKmh < 70) {
            return (float) $c->speed_factor_high;
        }

        return (float) $c->speed_factor_very_high;
    }

    public static function dwellModifier(int $exposureSeconds, ImpressionCoefficient $c): float
    {
        $shortMax = (int) config('impression_engine.calculation.dwell_short_max_seconds', 899);
        $medMax = (int) config('impression_engine.calculation.dwell_medium_max_seconds', 3600);
        if ($exposureSeconds <= $shortMax) {
            return (float) $c->dwell_factor_short;
        }
        if ($exposureSeconds <= $medMax) {
            return (float) $c->dwell_factor_medium;
        }

        return (float) $c->dwell_factor_long;
    }

    /**
     * @param  array<string, mixed>  $mobility
     */
    public static function drivingImpressions(
        int $exposureSeconds,
        int $hourLocal,
        float $avgSpeedKmh,
        array $mobility,
        ImpressionCoefficient $coeff
    ): float {
        $f = self::hourlyFlows($mobility, $hourLocal);
        $audience =
            $f['vehicle_hourly'] * (float) $coeff->vehicle_visibility_share
            + $f['pedestrian_hourly'] * (float) $coeff->pedestrian_visibility_share;
        $mod = self::speedModifier($avgSpeedKmh, $coeff);

        return ($exposureSeconds / 3600.0) * $audience * $mod;
    }

    /**
     * @param  array<string, mixed>  $mobility
     */
    public static function parkingImpressions(
        int $exposureSeconds,
        int $hourLocal,
        array $mobility,
        ImpressionCoefficient $coeff
    ): float {
        $f = self::hourlyFlows($mobility, $hourLocal);
        $audience =
            $f['pedestrian_hourly'] * (float) $coeff->pedestrian_parking_share
            + $f['vehicle_hourly'] * (float) $coeff->roadside_vehicle_share;
        $mod = self::dwellModifier($exposureSeconds, $coeff);

        return ($exposureSeconds / 3600.0) * $audience * $mod;
    }
}
