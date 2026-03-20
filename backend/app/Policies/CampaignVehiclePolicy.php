<?php

namespace App\Policies;

use App\Models\CampaignVehicle;
use App\Models\User;

class CampaignVehiclePolicy
{
    public function delete(User $user, CampaignVehicle $campaignVehicle): bool
    {
        $campaign = $campaignVehicle->campaign;

        return $user->isAdvertiser() && $campaign && $campaign->advertiser_id === $user->id;
    }
}
