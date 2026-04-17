<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Telemetry\DailyImpressionAggregateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetryAggregateDailyCommand extends Command
{
    protected $signature = 'telemetry:aggregate-daily
                            {--date= : Calendar day (YYYY-MM-DD), default yesterday}
                            {--campaign= : Only rebuild daily_impressions / daily_zone_impressions for this campaign id}';

    protected $description = 'Rebuild daily_impressions and daily_zone_impressions for a day.';

    public function handle(DailyImpressionAggregateService $aggregator): int
    {
        $opt = $this->option('date');
        $date = is_string($opt) && $opt !== ''
            ? Carbon::parse($opt, 'UTC')->startOfDay()
            : Carbon::yesterday('UTC')->startOfDay();

        $campaignOpt = $this->option('campaign');
        $onlyCampaignId = null;
        if (is_string($campaignOpt) && $campaignOpt !== '' && is_numeric($campaignOpt)) {
            $onlyCampaignId = (int) $campaignOpt;
            if (! Campaign::query()->whereKey($onlyCampaignId)->exists()) {
                $this->error('Campaign not found for --campaign='.$onlyCampaignId);

                return self::FAILURE;
            }
        }

        $aggregator->aggregateForDate($date, $onlyCampaignId);
        $this->info("Aggregated telemetry for {$date->toDateString()}".($onlyCampaignId !== null ? " (campaign {$onlyCampaignId})" : '').'.');

        return self::SUCCESS;
    }
}
