<?php

namespace App\Services\Telemetry;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared grid aggregation: per-cell moving vs stopped counts (same rules as {@see DeviceLocationMotionScope}).
 */
final class DeviceLocationHeatmapBuckets
{
    /**
     * @return Collection<int, object{lat: float, lng: float, w_moving: int, w_stopped: int}>
     */
    public static function groupedDualCounts(Builder $q): Collection
    {
        $threshold = (float) config('telemetry.parking_speed_kmh_max');
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            return $q
                ->selectRaw(
                    'ROUND(CAST(latitude AS numeric), 3)::float AS lat, ROUND(CAST(longitude AS numeric), 3)::float AS lng, '
                    .'SUM(CASE WHEN ignition IS NOT DISTINCT FROM false THEN 1 WHEN speed IS NOT NULL AND speed <= ? THEN 1 ELSE 0 END) AS w_stopped, '
                    .'SUM(CASE WHEN ignition IS NOT DISTINCT FROM false THEN 0 WHEN speed IS NOT NULL AND speed <= ? THEN 0 ELSE 1 END) AS w_moving',
                    [$threshold, $threshold]
                )
                ->groupByRaw('ROUND(CAST(latitude AS numeric), 3), ROUND(CAST(longitude AS numeric), 3)')
                ->get();
        }

        return $q
            ->selectRaw(
                'ROUND(latitude, 3) AS lat, ROUND(longitude, 3) AS lng, '
                .'SUM(CASE WHEN ignition = 0 THEN 1 WHEN speed IS NOT NULL AND speed <= ? THEN 1 ELSE 0 END) AS w_stopped, '
                .'SUM(CASE WHEN ignition = 0 THEN 0 WHEN speed IS NOT NULL AND speed <= ? THEN 0 ELSE 1 END) AS w_moving',
                [$threshold, $threshold]
            )
            ->groupByRaw('ROUND(latitude, 3), ROUND(longitude, 3)')
            ->get();
    }
}
