<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function view(User $user, Vehicle $vehicle): bool
    {
        if ($user->isMediaOwner()) {
            return $vehicle->media_owner_id === $user->id;
        }

        if ($user->isAdvertiser()) {
            if (in_array($vehicle->status, Vehicle::catalogVisibleStatuses(), true)) {
                return true;
            }

            if ($vehicle->status === Vehicle::STATUS_IN_CAMPAIGN) {
                return $vehicle->campaigns()->where('advertiser_id', $user->id)->exists();
            }

            return false;
        }

        return false;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isMediaOwner() && $vehicle->media_owner_id === $user->id;
    }
}
