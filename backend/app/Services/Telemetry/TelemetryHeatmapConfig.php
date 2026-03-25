<?php

namespace App\Services\Telemetry;

use App\Models\PlatformSetting;
use Carbon\Carbon;

/**
 * Admin-editable heatmap options (Filament → platform_settings). Env/config is fallback when unset.
 */
final class TelemetryHeatmapConfig
{
    public const KEY_INTENSITY_GAMMA = 'telemetry_heatmap_intensity_gamma';

    public const KEY_ADVERTISER_TRIPS_PER_VEHICLE_FULL_DAY = 'advertiser_heatmap_trips_per_vehicle_full_day';

    public const KEY_GLOBAL_DEFAULT_NORMALIZATION = 'telemetry_heatmap_global_default_normalization';

    public const KEY_GLOBAL_DEFAULT_MAP_VIEW = 'telemetry_heatmap_global_default_map_view';

    public const KEY_GLOBAL_DEFAULT_SHADOW = 'telemetry_heatmap_global_default_shadow';

    public static function intensityGamma(): float
    {
        $row = PlatformSetting::get(self::KEY_INTENSITY_GAMMA);
        if ($row !== null && $row !== '') {
            $g = (float) $row;

            return max(1.0, min(3.0, $g));
        }

        $g = (float) config('telemetry.heatmap.intensity_gamma', 1.55);

        return max(1.0, min(3.0, $g));
    }

    public static function tripsPerVehicleFullDay(): float
    {
        $row = PlatformSetting::get(self::KEY_ADVERTISER_TRIPS_PER_VEHICLE_FULL_DAY);
        if ($row !== null && $row !== '' && is_numeric($row)) {
            $v = (float) $row;

            return max(0.0, min(1000.0, $v));
        }

        $v = (float) config('telemetry.heatmap.advertiser_trips_per_vehicle_full_day', 1.0);

        return max(0.0, min(1000.0, $v));
    }

    /** @return 'max'|'p95'|'p99' */
    public static function defaultNormalization(): string
    {
        $row = PlatformSetting::get(self::KEY_GLOBAL_DEFAULT_NORMALIZATION);
        if (is_string($row) && in_array($row, ['max', 'p95', 'p99'], true)) {
            return $row;
        }

        $c = (string) config('telemetry.heatmap.global_default_normalization', 'max');

        return in_array($c, ['max', 'p95', 'p99'], true) ? $c : 'max';
    }

    /** @return 'heatmap'|'grid' */
    public static function defaultMapView(): string
    {
        $row = PlatformSetting::get(self::KEY_GLOBAL_DEFAULT_MAP_VIEW);
        if ($row === 'grid' || $row === 'heatmap') {
            return $row;
        }

        $c = (string) config('telemetry.heatmap.global_default_map_view', 'heatmap');

        return $c === 'grid' ? 'grid' : 'heatmap';
    }

    /** @return 'current'|'small'|'xsmall' */
    public static function defaultShadowPreset(): string
    {
        $row = PlatformSetting::get(self::KEY_GLOBAL_DEFAULT_SHADOW);
        if (is_string($row) && in_array($row, ['current', 'small', 'xsmall'], true)) {
            return $row;
        }

        $c = (string) config('telemetry.heatmap.global_default_shadow', 'xsmall');

        return in_array($c, ['current', 'small', 'xsmall'], true) ? $c : 'xsmall';
    }

    /**
     * Inclusive calendar-day count (UTC date strings Y-m-d).
     */
    public static function fullCalendarDaysInclusive(string $dateFrom, string $dateTo): int
    {
        $a = Carbon::parse($dateFrom, 'UTC')->startOfDay();
        $b = Carbon::parse($dateTo, 'UTC')->startOfDay();
        if ($b->lt($a)) {
            return 0;
        }

        return (int) $a->diffInDays($b) + 1;
    }

    /**
     * @return array{
     *     trips: int|null,
     *     heatmap_selection: array{
     *         date_from: string,
     *         date_to: string,
     *         full_calendar_days: int,
     *         vehicle_count: int,
     *         trips_per_vehicle_full_day: float
     *     }|null
     * }
     */
    public static function computeAdvertiserTripsKpi(string $dateFrom, string $dateTo, int $vehicleCount): array
    {
        $fullDays = self::fullCalendarDaysInclusive($dateFrom, $dateTo);
        $m = self::tripsPerVehicleFullDay();
        $trips = (int) round($m * max(0, $vehicleCount) * max(0, $fullDays));

        return [
            'trips' => $trips,
            'heatmap_selection' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'full_calendar_days' => $fullDays,
                'vehicle_count' => max(0, $vehicleCount),
                'trips_per_vehicle_full_day' => $m,
            ],
        ];
    }

    /**
     * @return array{
     *     intensity_gamma: string,
     *     advertiser_trips_per_vehicle_full_day: string,
     *     global_default_normalization: string,
     *     global_default_map_view: string,
     *     global_default_shadow: string
     * }
     */
    public static function allForForm(): array
    {
        $g = self::intensityGamma();
        $s = number_format($g, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.') ?: '1';

        $t = self::tripsPerVehicleFullDay();
        $ts = number_format($t, 2, '.', '');
        $ts = rtrim(rtrim($ts, '0'), '.') ?: '0';

        return [
            'intensity_gamma' => $s,
            'advertiser_trips_per_vehicle_full_day' => $ts,
            'global_default_normalization' => self::defaultNormalization(),
            'global_default_map_view' => self::defaultMapView(),
            'global_default_shadow' => self::defaultShadowPreset(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveFromForm(array $data): void
    {
        $g = (float) ($data['intensity_gamma'] ?? 1.55);
        $g = max(1.0, min(3.0, $g));
        PlatformSetting::set(self::KEY_INTENSITY_GAMMA, (string) $g);

        $t = (float) ($data['advertiser_trips_per_vehicle_full_day'] ?? 1.0);
        $t = max(0.0, min(1000.0, $t));
        PlatformSetting::set(self::KEY_ADVERTISER_TRIPS_PER_VEHICLE_FULL_DAY, (string) $t);

        $norm = (string) ($data['global_default_normalization'] ?? 'max');
        PlatformSetting::set(self::KEY_GLOBAL_DEFAULT_NORMALIZATION, in_array($norm, ['max', 'p95', 'p99'], true) ? $norm : 'max');

        $mv = (string) ($data['global_default_map_view'] ?? 'heatmap');
        PlatformSetting::set(self::KEY_GLOBAL_DEFAULT_MAP_VIEW, $mv === 'grid' ? 'grid' : 'heatmap');

        $sh = (string) ($data['global_default_shadow'] ?? 'xsmall');
        PlatformSetting::set(self::KEY_GLOBAL_DEFAULT_SHADOW, in_array($sh, ['current', 'small', 'xsmall'], true) ? $sh : 'xsmall');
    }
}
