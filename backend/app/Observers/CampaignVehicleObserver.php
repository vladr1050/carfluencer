<?php

namespace App\Observers;

use App\Models\CampaignVehicle;
use App\Models\Vehicle;

class CampaignVehicleObserver
{
    public function created(CampaignVehicle $campaignVehicle): void
    {
        $vehicle = Vehicle::query()->find($campaignVehicle->vehicle_id);
        if ($vehicle === null) {
            return;
        }

        // Any link to a campaign means fleet row should read "In campaign" (except explicit not_available).
        if ($vehicle->status === Vehicle::STATUS_NOT_AVAILABLE) {
            return;
        }

        if ($vehicle->status !== Vehicle::STATUS_IN_CAMPAIGN) {
            $vehicle->update(['status' => Vehicle::STATUS_IN_CAMPAIGN]);
        }
    }

    public function deleted(CampaignVehicle $campaignVehicle): void
    {
        $vehicle = Vehicle::query()->find($campaignVehicle->vehicle_id);
        if ($vehicle === null || $vehicle->status !== Vehicle::STATUS_IN_CAMPAIGN) {
            return;
        }

        $remaining = CampaignVehicle::query()->where('vehicle_id', $vehicle->id)->count();
        if ($remaining === 0) {
            $vehicle->update(['status' => Vehicle::STATUS_ACTIVE]);
        }
    }
}
