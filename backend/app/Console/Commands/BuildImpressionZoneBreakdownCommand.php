<?php

namespace App\Console\Commands;

use App\Jobs\BuildCampaignImpressionZoneBreakdownJob;
use App\Models\CampaignImpressionStat;
use Illuminate\Console\Command;

class BuildImpressionZoneBreakdownCommand extends Command
{
    protected $signature = 'impression:zone-breakdown {stat_id : campaign_impression_stats.id}';

    protected $description = 'Queue background build of top Geo zone breakdown JSON for a done impression snapshot';

    public function handle(): int
    {
        $id = (int) $this->argument('stat_id');
        if (! CampaignImpressionStat::query()->whereKey($id)->exists()) {
            $this->error('Campaign impression stat not found.');

            return self::FAILURE;
        }

        BuildCampaignImpressionZoneBreakdownJob::dispatch($id);
        $this->info('Zone breakdown job queued (unique within ~1h). Ensure queue workers are running.');

        return self::SUCCESS;
    }
}
