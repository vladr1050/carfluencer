<?php

namespace App\Services\Telemetry;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Index-friendly bounds on {@see DeviceLocation::event_at} (avoids wrapping event_at in DATE()).
 */
final class DeviceLocationEventAtRange
{
    public static function apply(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        if ($dateFrom !== null && $dateFrom !== '') {
            $query->where('event_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo !== null && $dateTo !== '') {
            $query->where('event_at', '<', Carbon::parse($dateTo)->addDay()->startOfDay());
        }
    }
}
