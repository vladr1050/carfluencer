<?php

namespace App\Console\Commands;

use App\Models\CampaignVehicle;
use App\Models\Vehicle;
use Illuminate\Console\Command;

/**
 * One-time / maintenance: align vehicle.status with campaign_vehicles rows (after migrations or imports).
 */
class VehiclesReconcileFleetStatusCommand extends Command
{
    protected $signature = 'vehicles:reconcile-fleet-status';

    protected $description = 'Set status to in_campaign when linked to any campaign; clear to active when no links (skips not_available).';

    public function handle(): int
    {
        $updated = 0;

        foreach (Vehicle::query()->cursor() as $vehicle) {
            $hasLink = CampaignVehicle::query()->where('vehicle_id', $vehicle->id)->exists();

            if ($hasLink) {
                if ($vehicle->status === Vehicle::STATUS_NOT_AVAILABLE) {
                    continue;
                }
                if ($vehicle->status !== Vehicle::STATUS_IN_CAMPAIGN) {
                    $vehicle->update(['status' => Vehicle::STATUS_IN_CAMPAIGN]);
                    $updated++;
                }

                continue;
            }

            if ($vehicle->status === Vehicle::STATUS_IN_CAMPAIGN) {
                $vehicle->update(['status' => Vehicle::STATUS_ACTIVE]);
                $updated++;
            }
        }

        $this->info("Reconciled {$updated} vehicle(s).");

        return self::SUCCESS;
    }
}
