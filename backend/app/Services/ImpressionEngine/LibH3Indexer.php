<?php

namespace App\Services\ImpressionEngine;

use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use MichaelLindahl\H3\H3;

/**
 * Uber H3 via FFI ({@see H3}). Requires libh3 on the host (brew/apt).
 */
final class LibH3Indexer implements H3IndexerInterface
{
    public function latLngToCellId(float $lat, float $lng, ?int $resolution = null): string
    {
        $res = $resolution ?? (int) config('impression_engine.h3.resolution', 9);
        $lib = $this->libraryPath();
        $h3 = new H3($lib);
        $hex = $h3->geoToH3($lat, $lng, $res);

        return self::normalizeH3Hex($hex);
    }

    public function cellIdToLatLng(string $cellId): array
    {
        $lib = $this->libraryPath();
        $h3 = new H3($lib);
        $geo = $h3->h3ToGeo($cellId);

        return ['lat' => (float) $geo->lat, 'lng' => (float) $geo->lon];
    }

    public function libraryPath(): string
    {
        $configured = config('impression_engine.h3.library_path');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return PHP_OS_FAMILY === 'Darwin' ? H3::DYLIB : H3::SO;
    }

    /**
     * php-h3 returns {@see dechex()} without leading zeros; pad for stable keys / JOIN.
     */
    public static function normalizeH3Hex(string $hex): string
    {
        $hex = strtolower(ltrim(trim($hex), '0x'));

        return str_pad($hex, 15, '0', STR_PAD_LEFT);
    }
}
