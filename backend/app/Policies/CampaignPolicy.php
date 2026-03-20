<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->isAdvertiser()) {
            return $campaign->advertiser_id === $user->id;
        }

        if ($user->isMediaOwner()) {
            return $campaign->vehicles()->where('vehicles.media_owner_id', $user->id)->exists();
        }

        return false;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->isAdvertiser() && $campaign->advertiser_id === $user->id;
    }

    /**
     * View heatmap and analytics for own campaigns.
     */
    public function viewAnalytics(User $user, Campaign $campaign): bool
    {
        return $user->isAdvertiser() && $campaign->advertiser_id === $user->id;
    }
}
