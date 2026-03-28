<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\LocationLabelProviderInterface;

final class NullLocationLabelProvider implements LocationLabelProviderInterface
{
    public function reverseLookup(float $lat, float $lng): ?array
    {
        return null;
    }
}
