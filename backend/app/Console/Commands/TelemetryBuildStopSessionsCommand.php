<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Telemetry\StopSessionBuilderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetryBuildStopSessionsCommand extends Command
{
    protected $signature = 'telemetry:build-stop-sessions
                            {--date= : Calendar day (YYYY-MM-DD), default yesterday}
                            {--campaign= : Only devices (IMEIs) linked to this campaign id}';

    protected $description = 'Build stop_sessions from device_locations (parking vs driving) and run zone attribution.';

    public function handle(StopSessionBuilderService $builder): int
    {
        $opt = $this->option('date');
        $date = is_string($opt) && $opt !== ''
            ? Carbon::parse($opt, 'UTC')->startOfDay()
            : Carbon::yesterday('UTC')->startOfDay();

        $resolved = $this->resolveCampaignDeviceIds($this->option('campaign'));
        if ($resolved === false) {
            return self::FAILURE;
        }

        $onlyDeviceIds = $resolved;
        if ($onlyDeviceIds !== null && $onlyDeviceIds === []) {
            $this->warn('No vehicle IMEIs on this campaign; created 0 session row(s).');

            return self::SUCCESS;
        }

        $n = $builder->buildForDate($date, $onlyDeviceIds);
        $this->info("Created {$n} session row(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null|false null = all devices; list (maybe empty) = restrict to IMEIs; false = campaign id invalid / not found
     */
    private function resolveCampaignDeviceIds(mixed $campaignOption): array|false|null
    {
        if (! is_string($campaignOption) || $campaignOption === '' || ! is_numeric($campaignOption)) {
            return null;
        }

        $id = (int) $campaignOption;
        $campaign = Campaign::query()
            ->with(['campaignVehicles.vehicle'])
            ->find($id);
        if ($campaign === null) {
            $this->error('Campaign not found for --campaign='.$id);

            return false;
        }

        return $campaign->campaignVehicles
            ->map(fn ($cv) => $cv->vehicle?->imei)
            ->filter(fn ($imei) => is_string($imei) && $imei !== '')
            ->unique()
            ->values()
            ->all();
    }
}
