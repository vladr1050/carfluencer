<?php

namespace App\Support\Geo;

/**
 * Official boundaries of Riga’s 58 neighbourhoods (apkaimes).
 *
 * Source: Latvijas atvērto datu portāls — “Rīgas apkaimes” (KML 2024-07-10),
 * https://data.gov.lv/dati/lv/dataset/rigas_apkaimes — CC BY 4.0.
 * Converted to GeoJSON (WGS84) for use in the admin map picker.
 */
final class RigaApkaimes
{
    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function featureCollection(): array
    {
        return once(static function (): array {
            $path = resource_path('geo/riga-apkaimes.json');
            $json = file_get_contents($path);
            if ($json === false) {
                throw new \RuntimeException('Missing Riga apkaimes GeoJSON at '.$path);
            }

            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        });
    }
}
