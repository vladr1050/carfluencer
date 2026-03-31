<?php

namespace App\Services\ImpressionEngine\Contracts;

interface H3IndexerInterface
{
    /**
     * Canonical H3 cell id string for (lat, lng) at configured or given resolution.
     */
    public function latLngToCellId(float $lat, float $lng, ?int $resolution = null): string;

    /**
     * Cell center in WGS84 (for spatial fallback / QC).
     *
     * @return array{lat: float, lng: float}
     */
    public function cellIdToLatLng(string $cellId): array;
}
