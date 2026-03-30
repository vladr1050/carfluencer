<?php

namespace App\Services\ImpressionEngine\Contracts;

interface H3IndexerInterface
{
    /**
     * Canonical H3 cell id string for (lat, lng) at configured or given resolution.
     */
    public function latLngToCellId(float $lat, float $lng, ?int $resolution = null): string;
}
