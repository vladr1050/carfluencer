<?php

namespace App\Policies;

use App\Models\CampaignReport;
use App\Models\User;

class CampaignReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CampaignReport $campaignReport): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CampaignReport $campaignReport): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CampaignReport $campaignReport): bool
    {
        return $user->isAdmin();
    }
}
