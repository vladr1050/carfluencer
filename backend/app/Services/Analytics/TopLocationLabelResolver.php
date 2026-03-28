<?php

namespace App\Services\Analytics;

use App\Models\LocationLabelCache;
use App\Services\Analytics\Contracts\LocationLabelProviderInterface;
use Throwable;

/**
 * Cached, provider-backed human labels for report top_locations (coordinates → short area names).
 */
final class TopLocationLabelResolver
{
    private const CACHE_ROUND_DECIMALS = 4;

    public function __construct(
        private readonly LocationLabelProviderInterface $locationLabelProvider,
    ) {}

    /**
     * Resolve a single point; never throws. Returns null when unavailable.
     */
    public function resolveForCoordinates(float $lat, float $lng): ?string
    {
        $providerKey = strtolower(trim((string) config('reports.location_labels.provider', 'none')));
        if ($providerKey === '' || $providerKey === 'none') {
            return null;
        }

        $latB = round($lat, self::CACHE_ROUND_DECIMALS);
        $lngB = round($lng, self::CACHE_ROUND_DECIMALS);

        $ttlDays = max(1, (int) config('reports.location_labels.cache_ttl_days', 90));
        $cutoff = now()->subDays($ttlDays);

        try {
            $cached = LocationLabelCache::query()
                ->where('provider', $providerKey)
                ->where('lat_bucket', $latB)
                ->where('lng_bucket', $lngB)
                ->first();

            if (
                $cached !== null
                && $cached->resolved_at !== null
                && $cached->resolved_at->greaterThan($cutoff)
                && is_string($cached->label)
                && $cached->label !== ''
            ) {
                return $cached->label;
            }

            $payload = $this->locationLabelProvider->reverseLookup($latB, $lngB);
            if ($payload === null) {
                return null;
            }

            $label = TopLocationLabelNormalizer::normalize($payload, $providerKey);
            if ($label === null || $label === '') {
                return null;
            }

            LocationLabelCache::query()->updateOrCreate(
                [
                    'lat_bucket' => $latB,
                    'lng_bucket' => $lngB,
                    'provider' => $providerKey,
                ],
                [
                    'label' => $label,
                    'raw_response_json' => $payload,
                    'resolved_at' => now(),
                ]
            );

            return $label;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $topLocations
     * @return list<array<string, mixed>>
     */
    public function enrichTopLocations(array $topLocations): array
    {
        if ($topLocations === []) {
            return [];
        }

        $delayMs = max(0, (int) config('reports.location_labels.inter_request_delay_ms', 1100));
        $out = [];
        foreach ($topLocations as $i => $loc) {
            $lat = (float) ($loc['lat'] ?? 0);
            $lng = (float) ($loc['lng'] ?? 0);
            $loc['label'] = $this->resolveForCoordinates($lat, $lng);
            $out[] = $loc;
            if ($i < count($topLocations) - 1 && $delayMs > 0 && $this->providerIsNetworked()) {
                usleep($delayMs * 1000);
            }
        }

        return $out;
    }

    private function providerIsNetworked(): bool
    {
        $p = strtolower(trim((string) config('reports.location_labels.provider', 'none')));

        return $p === 'nominatim';
    }
}
