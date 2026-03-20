<?php

namespace App\Services\Pricing;

use App\Models\AdPlacementPolicy;

class PlacementPricingService
{
    /**
     * Resolve base price from active policy for the given placement size class (S, M, L, XL).
     */
    public function resolveBasePrice(string $sizeClass): ?string
    {
        return AdPlacementPolicy::basePriceForSize($sizeClass);
    }
}
