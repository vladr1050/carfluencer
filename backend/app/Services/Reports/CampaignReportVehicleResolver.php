<?php

namespace App\Services\Reports;

use App\Models\Campaign;

/**
 * Resolves the vehicle set for a campaign report once; same ids must be passed to all metrics and heatmaps.
 */
final class CampaignReportVehicleResolver
{
    /**
     * @return list<int>
     */
    public function resolveForCampaign(int $campaignId): array
    {
        return Campaign::query()
            ->findOrFail($campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
