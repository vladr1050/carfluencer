<?php

namespace App\Services\ImpressionEngine;

use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use FFI;
use InvalidArgumentException;
use MichaelLindahl\H3\H3;

/**
 * Uber H3 via FFI ({@see H3}). Requires libh3 on the host (brew/apt).
 *
 * {@see cellIdToLatLng} avoids michaellindahl/php-h3's h3ToGeo(): it uses hexdec() on the index,
 * which loses precision for 64-bit H3 cells and breaks mobility fallback + zone breakdown on PHP.
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
        $normalized = self::normalizeCellIdForIndex($cellId);
        if (str_starts_with($normalized, 'fake_')) {
            throw new InvalidArgumentException('LibH3Indexer cannot decode fake H3 cell ids.');
        }

        $h3Index = self::h3HexStringToFfiIndex($normalized);

        $ffi = FFI::cdef(
            'typedef uint64_t H3Index;
            typedef struct { double lat; double lon; } GeoCoord;
            void h3ToGeo(H3Index h3, GeoCoord *g);',
            $this->libraryPath()
        );

        $geoCoord = $ffi->new('GeoCoord');
        $ffi->h3ToGeo($h3Index, FFI::addr($geoCoord));

        return [
            'lat' => rad2deg((float) $geoCoord->lat),
            'lng' => rad2deg((float) $geoCoord->lon),
        ];
    }

    /**
     * Convert canonical hex cell id to a value FFI passes as H3Index (no float precision loss).
     */
    public static function h3HexStringToFfiIndex(string $normalizedHex): int
    {
        $hex = strtolower(ltrim(trim($normalizedHex), '0x'));
        if ($hex === '' || ! ctype_xdigit($hex)) {
            throw new InvalidArgumentException('Invalid H3 cell id (expected hex).');
        }
        if (strlen($hex) > 16) {
            throw new InvalidArgumentException('H3 hex string too long.');
        }

        $hex = str_pad($hex, 16, '0', STR_PAD_LEFT);
        $bin = hex2bin($hex);
        if ($bin === false || strlen($bin) !== 8) {
            throw new InvalidArgumentException('Invalid H3 hex encoding.');
        }

        $unpacked = unpack('J', $bin);

        return (int) $unpacked[1];
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

    /**
     * Canonical H3 index string for lookups against DB and FFI (skip test fake ids).
     */
    public static function normalizeCellIdForIndex(string $cellId): string
    {
        $cellId = trim($cellId);

        if (str_starts_with($cellId, 'fake_')) {
            return $cellId;
        }

        return self::normalizeH3Hex($cellId);
    }
}
