<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\LocationLabelProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenStreetMap Nominatim reverse geocoding (report labels only).
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
final class NominatimLocationLabelProvider implements LocationLabelProviderInterface
{
    public function reverseLookup(float $lat, float $lng): ?array
    {
        $timeout = max(1, (int) config('reports.location_labels.timeout_seconds', 5));
        $base = rtrim((string) config('reports.location_labels.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');
        $ua = (string) config('reports.location_labels.nominatim.user_agent', '');
        if ($ua === '') {
            $ua = 'EvoCampaignReports/1.0';
        }

        $url = $base.'/reverse';
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => $ua,
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                ])
                ->get($url, [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 14,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (Throwable $e) {
            Log::debug('Nominatim reverse geocode failed', [
                'lat' => $lat,
                'lng' => $lng,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
