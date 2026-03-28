<?php

namespace App\Services\Telemetry;

/**
 * Leaflet.heat + basemap — те же константы, что в
 * {@see resources/views/filament/pages/telemetry-heatmap-body.blade.php} и
 * {@see frontend/src/app/pages/advertiser/Heatmap.tsx}.
 */
final class HeatmapLeafletStyle
{
    private const HEAT_MAX_DENOM_MOVING = 2.15;

    private const HEAT_MIN_OPACITY = 0.16;

    private const PARKING_HEAT_MIN_OPACITY = 0.27;

    private const PARKING_HEAT_MAX_DENOM = 1.82;

    /** @var array<string, array{moving: array{radius: int, blur: int}, parking: array{radius: int, blur: int}}> */
    private const SHADOW_PRESETS = [
        'current' => [
            'moving' => ['radius' => 24, 'blur' => 14],
            'parking' => ['radius' => 40, 'blur' => 21],
        ],
        'small' => [
            'moving' => ['radius' => 10, 'blur' => 5],
            'parking' => ['radius' => 16, 'blur' => 8],
        ],
        'xsmall' => [
            'moving' => ['radius' => 8, 'blur' => 3],
            'parking' => ['radius' => 12, 'blur' => 6],
        ],
    ];

    /**
     * @return array{url: string, attribution: string, subdomains: string|null, max_zoom: int}
     */
    public static function tileLayerConfig(): array
    {
        $key = config('services.maptiler.api_key');
        if (filled($key)) {
            return [
                'url' => 'https://api.maptiler.com/maps/positron/{z}/{x}/{y}.png?key='.rawurlencode((string) $key),
                'attribution' => '<a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'subdomains' => null,
                'max_zoom' => 20,
            ];
        }

        return [
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            'subdomains' => 'abcd',
            'max_zoom' => 20,
        ];
    }

    /**
     * String keys — PHP casts float array keys to int and would collapse 0.25/0.5/0.75 to 0.
     *
     * @return array<string, string>
     */
    public static function gradientMoving(): array
    {
        return [
            '0' => '#440154',
            '0.25' => '#3b528b',
            '0.5' => '#21918c',
            '0.75' => '#5ec962',
            '1' => '#fde725',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function gradientStopped(): array
    {
        return [
            '0' => '#1b5e20',
            '0.22' => '#43a047',
            '0.45' => '#c6d84a',
            '0.62' => '#ffeb3b',
            '0.8' => '#fb8c00',
            '1' => '#c62828',
        ];
    }

    /**
     * Preset for PDF/PNG export: env CAMPAIGN_REPORT_HEATMAP_SHADOW, else platform default (как у портала).
     *
     * @return 'current'|'small'|'xsmall'
     */
    public static function shadowPresetForReport(): string
    {
        $raw = config('reports.heatmap_export.shadow_preset');
        if (is_string($raw) && in_array($raw, ['current', 'small', 'xsmall'], true)) {
            return $raw;
        }

        return TelemetryHeatmapConfig::defaultShadowPreset();
    }

    /**
     * Опции L.heatLayer, совместимые с админкой / Advertiser heatmap view.
     *
     * @return array{
     *     radius: int,
     *     blur: int,
     *     maxZoom: int,
     *     minOpacity: float,
     *     max: float,
     *     gradient: array<string, string>
     * }
     */
    public static function heatLayerOptionsForExport(string $mode): array
    {
        $preset = self::shadowPresetForReport();
        $dims = self::SHADOW_PRESETS[$preset] ?? self::SHADOW_PRESETS['current'];

        if ($mode === 'parking') {
            $g = self::gradientStopped();
            $p = $dims['parking'];

            return [
                'radius' => $p['radius'],
                'blur' => $p['blur'],
                'maxZoom' => 17,
                'minOpacity' => self::PARKING_HEAT_MIN_OPACITY,
                'max' => 1.0 / self::PARKING_HEAT_MAX_DENOM,
                'gradient' => self::floatKeysToJsonGradient($g),
            ];
        }

        /** Driving: same as {@see frontend/src/app/pages/advertiser/Heatmap.tsx} (moving heat). */
        $g = self::gradientMoving();
        $m = $dims['moving'];

        return [
            'radius' => $m['radius'],
            'blur' => $m['blur'],
            'maxZoom' => 17,
            'minOpacity' => self::HEAT_MIN_OPACITY,
            'max' => 1.0 / self::HEAT_MAX_DENOM_MOVING,
            'gradient' => self::floatKeysToJsonGradient($g),
        ];
    }

    /**
     * JSON object keys must be strings; leaflet.heat accepts numeric string keys.
     *
     * @param  array<string, string>  $g
     * @return array<string, string>
     */
    private static function floatKeysToJsonGradient(array $g): array
    {
        $out = [];
        foreach ($g as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }
}
