<?php

namespace App\Services\Telemetry;

use App\Models\User;
use App\Models\Vehicle;

final class TelemetryGate
{
    public static function canAccessImei(User $user, string $imei): bool
    {
        $vehicle = Vehicle::query()->where('imei', $imei)->first();
        if ($vehicle === null) {
            return false;
        }

        if ($user->isMediaOwner() && $vehicle->media_owner_id === $user->id) {
            return true;
        }

        if ($user->isAdvertiser()) {
            return $vehicle->campaigns()
                ->where('campaigns.advertiser_id', $user->id)
                ->exists();
        }

        return false;
    }

    public static function advertiserOwnsCampaign(User $user, int $campaignId): bool
    {
        if (! $user->isAdvertiser()) {
            return false;
        }

        return \App\Models\Campaign::query()
            ->where('id', $campaignId)
            ->where('advertiser_id', $user->id)
            ->exists();
    }
}
