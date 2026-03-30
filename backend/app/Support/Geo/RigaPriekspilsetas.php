<?php

namespace App\Support\Geo;

/**
 * Official boundaries of Riga’s six administrative districts (priekšpilsētas / rajoni).
 *
 * Source: Latvijas atvērto datu portāls — “Rīgas un Rīgas priekšpilsētu robežas” (KML 2024-07-10),
 * https://data.gov.lv/dati/lv/dataset/rigas-un-priekspilsetu-robezas — CC BY 4.0.
 * Converted to GeoJSON (WGS84) for use in the admin map picker.
 */
final class RigaPriekspilsetas
{
    /**
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function featureCollection(): array
    {
        return once(static function (): array {
            $path = resource_path('geo/riga-priekspilsetas.json');
            $json = file_get_contents($path);
            if ($json === false) {
                throw new \RuntimeException('Missing Riga districts GeoJSON at '.$path);
            }

            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        });
    }
}
