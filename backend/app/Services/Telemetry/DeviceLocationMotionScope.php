<?php

namespace App\Services\Telemetry;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filters device_locations by motion, same rules as {@see StopSessionBuilderService::isParking()}.
 */
final class DeviceLocationMotionScope
{
    /**
     * @param  string  $motion  admin API: both | moving | stopped
     */
    public static function apply(Builder $query, string $motion): void
    {
        if ($motion === 'both') {
            return;
        }

        $t = (float) config('telemetry.parking_speed_kmh_max');

        if ($motion === 'stopped') {
            $query->where(function ($w) use ($t): void {
                $w->where('ignition', false)
                    ->orWhere(function ($w2) use ($t): void {
                        $w2->whereNotNull('speed')->where('speed', '<=', $t);
                    });
            });

            return;
        }

        if ($motion === 'moving') {
            $query->where(function ($w) use ($t): void {
                $w->where(function ($w2): void {
                    $w2->whereNull('ignition')->orWhere('ignition', true);
                })->where(function ($w2) use ($t): void {
                    $w2->whereNull('speed')->orWhere('speed', '>', $t);
                });
            });
        }
    }

    /**
     * Advertiser heatmap API: parking | driving | both.
     */
    public static function applyAdvertiserMode(Builder $query, string $mode): void
    {
        $motion = match ($mode) {
            'parking' => 'stopped',
            'driving' => 'moving',
            default => 'both',
        };

        self::apply($query, $motion);
    }
}
