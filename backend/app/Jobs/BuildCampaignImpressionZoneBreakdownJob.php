<?php

namespace App\Jobs;

use App\Models\CampaignImpressionStat;
use App\Services\ImpressionEngine\CampaignImpressionGeoZoneBreakdownService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Heavy pass over hourly exposure for top Geo zones; must not run inside a web request for large snapshots.
 */
class BuildCampaignImpressionZoneBreakdownJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $campaignImpressionStatId,
    ) {}

    public function uniqueId(): string
    {
        return 'campaign_impression_zone_breakdown_'.$this->campaignImpressionStatId;
    }

    public function handle(CampaignImpressionGeoZoneBreakdownService $zones): void
    {
        $stat = CampaignImpressionStat::query()->find($this->campaignImpressionStatId);
        if ($stat === null) {
            return;
        }

        if ($stat->status !== CampaignImpressionStat::STATUS_DONE) {
            return;
        }

        try {
            $payload = $zones->topZonesForSnapshot($stat, 10);
            $stat->update([
                'zone_breakdown_json' => $payload,
            ]);
        } catch (Throwable $e) {
            Log::error('Impression zone breakdown job failed', [
                'campaign_impression_stat_id' => $this->campaignImpressionStatId,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
