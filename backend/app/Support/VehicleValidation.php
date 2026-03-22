<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class VehicleValidation
{
    /**
     * @return array<int, mixed>
     */
    public static function colorKeyRules(bool $required = false): array
    {
        $keys = array_keys(config('vehicle.colors', []));

        return array_merge(
            $required ? ['required'] : ['nullable'],
            ['string', 'max:40', Rule::in($keys)]
        );
    }

    /**
     * @return array<int, mixed>
     */
    public static function fleetStatusRules(bool $required = false): array
    {
        return array_merge(
            $required ? ['required'] : ['nullable'],
            ['string', 'max:32', Rule::in(Vehicle::STATUSES)]
        );
    }
}
