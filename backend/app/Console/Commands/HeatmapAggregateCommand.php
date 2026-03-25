<?php

namespace App\Console\Commands;

use App\Services\Telemetry\HeatmapAggregationService;
use Illuminate\Console\Command;

class HeatmapAggregateCommand extends Command
{
    protected $signature = 'heatmap:aggregate
                            {--from= : Start date Y-m-d (UTC)}
                            {--to= : End date Y-m-d (UTC, inclusive)}
                            {--day= : Single day Y-m-d (UTC); alternative to from/to}
                            {--tier= : Only this zoom tier index (0-based)}
                            {--mode=driving : driving or parking}
                            {--all-modes : Aggregate both driving and parking (ignores --mode)}
                            {--rebuild : Same as running aggregate for the given range}';

    protected $description = 'Build heatmap_cells_daily rollups from device_locations (PostgreSQL).';

    public function handle(HeatmapAggregationService $aggregation): int
    {
        $day = $this->option('day');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($day) {
            $from = $to = $day;
        }

        if ($from === null || $from === '' || $to === null || $to === '') {
            $this->error('Provide --day=Y-m-d or both --from= and --to=');

            return self::FAILURE;
        }

        $onlyTier = $this->option('tier');
        $onlyTier = $onlyTier !== null && $onlyTier !== '' ? (int) $onlyTier : null;

        $allModes = (bool) $this->option('all-modes');
        $modeOpt = (string) $this->option('mode');
        $onlyMode = $allModes ? null : (in_array($modeOpt, [HeatmapAggregationService::MODE_DRIVING, HeatmapAggregationService::MODE_PARKING], true)
            ? $modeOpt
            : null);

        if (! $allModes && $onlyMode === null) {
            $this->error('--mode must be driving or parking, or use --all-modes');

            return self::FAILURE;
        }

        $this->info("Aggregating {$from} … {$to}".($onlyTier !== null ? " tier={$onlyTier}" : ' (all tiers)').($onlyMode ? " mode={$onlyMode}" : ' (all modes)'));

        $aggregation->aggregateRange($from, $to, $onlyTier, $onlyMode);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
