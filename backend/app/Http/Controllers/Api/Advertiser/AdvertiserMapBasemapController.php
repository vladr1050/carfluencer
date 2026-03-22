<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Positron-style basemap config — must stay in sync with
 * resources/views/filament/pages/telemetry-heatmap-body.blade.php (admin heatmap).
 */
class AdvertiserMapBasemapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $key = config('services.maptiler.api_key');
        if (filled($key)) {
            return response()->json([
                'provider' => 'maptiler',
                'url' => 'https://api.maptiler.com/maps/positron/{z}/{x}/{y}.png?key='.rawurlencode((string) $key),
                'attribution' => '<a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'subdomains' => null,
                'max_zoom' => 20,
            ]);
        }

        return response()->json([
            'provider' => 'carto',
            'url' => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            'subdomains' => 'abcd',
            'max_zoom' => 20,
        ]);
    }
}
