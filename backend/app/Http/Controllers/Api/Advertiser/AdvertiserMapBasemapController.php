<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\TelemetryHeatmapConfig;
use Illuminate\Http\JsonResponse;

/**
 * Positron-style basemap config — must stay in sync with
 * resources/views/filament/pages/telemetry-heatmap-body.blade.php (admin heatmap).
 */
class AdvertiserMapBasemapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $displayDefaults = [
            'normalization' => TelemetryHeatmapConfig::defaultNormalization(),
            'map_view' => TelemetryHeatmapConfig::defaultMapView(),
            'shadow_preset' => TelemetryHeatmapConfig::defaultShadowPreset(),
        ];

        $key = config('services.maptiler.api_key');
        if (filled($key)) {
            return response()->json([
                'provider' => 'maptiler',
                'url' => 'https://api.maptiler.com/maps/positron/{z}/{x}/{y}.png?key='.rawurlencode((string) $key),
                'attribution' => '<a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'subdomains' => null,
                'max_zoom' => 20,
                'display_defaults' => $displayDefaults,
            ]);
        }

        return response()->json([
            'provider' => 'carto',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            'subdomains' => 'abcd',
            'max_zoom' => 20,
            'display_defaults' => $displayDefaults,
        ]);
    }
}
