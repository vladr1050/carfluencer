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
            return $vehicle->status === 'active';
        }

        return false;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isMediaOwner() && $vehicle->media_owner_id === $user->id;
    }
}
