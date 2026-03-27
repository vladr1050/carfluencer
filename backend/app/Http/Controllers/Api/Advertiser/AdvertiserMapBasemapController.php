<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\HeatmapLeafletStyle;
use App\Services\Telemetry\TelemetryHeatmapConfig;
use Illuminate\Http\JsonResponse;

/**
 * Basemap + display defaults for advertiser heatmap — tile URL совпадает с PDF-экспортом отчётов
 * ({@see HeatmapLeafletStyle::tileLayerConfig}).
 */
class AdvertiserMapBasemapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tile = HeatmapLeafletStyle::tileLayerConfig();

        $displayDefaults = [
            'normalization' => TelemetryHeatmapConfig::defaultNormalization(),
            'map_view' => TelemetryHeatmapConfig::defaultMapView(),
            'shadow_preset' => TelemetryHeatmapConfig::defaultShadowPreset(),
        ];

        $provider = filled(config('services.maptiler.api_key')) ? 'maptiler' : 'carto';

        return response()->json([
            'provider' => $provider,
            'url' => $tile['url'],
            'attribution' => $tile['attribution'],
            'subdomains' => $tile['subdomains'],
            'max_zoom' => $tile['max_zoom'],
            'display_defaults' => $displayDefaults,
        ]);
    }
}
