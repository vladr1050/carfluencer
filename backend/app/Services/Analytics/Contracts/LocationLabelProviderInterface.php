<?php

namespace App\Services\Analytics\Contracts;

interface LocationLabelProviderInterface
{
    /**
     * Reverse lookup for presentation labels. Returns raw provider payload for normalization.
     *
     * @return array<string, mixed>|null Typical keys: address (array), display_name (string)
     */
    public function reverseLookup(float $lat, float $lng): ?array;
}
