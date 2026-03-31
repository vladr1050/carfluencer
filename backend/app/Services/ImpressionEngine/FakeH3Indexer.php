<?php

namespace App\Services\ImpressionEngine;

use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;

/**
 * Deterministic pseudo-cell ids for PHPUnit / CI without libh3.
 */
final class FakeH3Indexer implements H3IndexerInterface
{
    public function latLngToCellId(float $lat, float $lng, ?int $resolution = null): string
    {
        $res = $resolution ?? (int) config('impression_engine.h3.resolution', 9);

        return 'fake_'.substr(hash('sha256', sprintf('%.6f|%.6f|%d', $lat, $lng, $res)), 0, 20);
    }

    public function cellIdToLatLng(string $cellId): array
    {
        return ['lat' => 56.95, 'lng' => 24.11];
    }
}
