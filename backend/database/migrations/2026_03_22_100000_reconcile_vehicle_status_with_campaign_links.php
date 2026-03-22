<?php

use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;

/**
 * Align vehicles.status with campaign_vehicles (existing rows may predate observer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Vehicle::query()
            ->whereHas('campaigns')
            ->where('status', '!=', Vehicle::STATUS_NOT_AVAILABLE)
            ->where('status', '!=', Vehicle::STATUS_IN_CAMPAIGN)
            ->update(['status' => Vehicle::STATUS_IN_CAMPAIGN]);

        Vehicle::query()
            ->where('status', Vehicle::STATUS_IN_CAMPAIGN)
            ->whereDoesntHave('campaigns')
            ->update(['status' => Vehicle::STATUS_ACTIVE]);
    }

    public function down(): void
    {
        // Non-reversible data fix.
    }
};
