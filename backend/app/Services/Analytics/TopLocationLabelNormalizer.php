<?php

namespace App\Services\Analytics;

/**
 * Turns provider JSON into a short advertiser-facing area label (not a full postal address).
 */
final class TopLocationLabelNormalizer
{
    private const MAX_LEN = 90;

    /**
     * @param  array<string, mixed>  $payload  Provider response (e.g. Nominatim JSON)
     */
    public static function normalize(array $payload, string $provider): ?string
    {
        return match ($provider) {
            'nominatim' => self::fromNominatim($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function fromNominatim(array $payload): ?string
    {
        /** @var array<string, mixed> $a */
        $a = isset($payload['address']) && is_array($payload['address']) ? $payload['address'] : [];

        $city = self::firstString($a, ['city', 'town', 'village', 'municipality', 'hamlet']);
        $suburb = self::firstString($a, ['suburb', 'neighbourhood', 'quarter', 'city_district']);
        $road = self::firstString($a, ['road', 'pedestrian', 'footway']);
        $name = self::firstString($a, ['name', 'aerodrome']);
        $type = isset($payload['type']) ? (string) $payload['type'] : '';
        $displayLower = strtolower((string) ($payload['display_name'] ?? ''));

        if ($type === 'aerodrome' || ($a['aeroway'] ?? null) === 'aerodrome' || ($a['amenity'] ?? null) === 'aerodrome') {
            $line = $name !== '' ? $name.' area' : ($city !== '' ? $city.' Airport area' : 'Airport area');
        } elseif (str_contains(strtolower($name), 'airport') || str_contains($displayLower, 'airport')) {
            $line = $name !== '' ? $name.' area' : 'Airport area';
        } elseif ($city !== '' && $road !== '') {
            $line = $city.' / '.$road.' area';
        } elseif ($city !== '' && $suburb !== '') {
            $line = $city.' / '.$suburb.' area';
        } elseif ($city !== '' && $suburb === '' && $road === '') {
            $line = $city.' central area';
        } elseif ($city !== '') {
            $line = $city.' area';
        } elseif ($suburb !== '') {
            $line = $suburb.' area';
        } elseif ($road !== '') {
            $line = $road.' area';
        } else {
            $display = isset($payload['display_name']) ? trim((string) $payload['display_name']) : '';
            if ($display === '') {
                return null;
            }
            $parts = array_map('trim', explode(',', $display));
            $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));
            $line = implode(' / ', array_slice($parts, 0, 2)).' area';
        }

        $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
        if (strlen($line) > self::MAX_LEN) {
            $line = substr($line, 0, self::MAX_LEN - 1).'…';
        }

        return $line !== '' ? $line : null;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  list<string>  $keys
     */
    private static function firstString(array $a, array $keys): string
    {
        foreach ($keys as $k) {
            if (! empty($a[$k]) && is_string($a[$k])) {
                return trim($a[$k]);
            }
        }

        return '';
    }
}
