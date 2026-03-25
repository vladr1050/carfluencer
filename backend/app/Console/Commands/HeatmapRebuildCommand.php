<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Alias for {@see HeatmapAggregateCommand} (full-range rebuild semantics).
 */
class HeatmapRebuildCommand extends Command
{
    protected $signature = 'heatmap:rebuild
                            {--from= : Start date Y-m-d (UTC)}
                            {--to= : End date Y-m-d (UTC, inclusive)}
                            {--tier= : Only this zoom tier index (0-based)}
                            {--mode=driving : driving or parking}
                            {--all-modes : Both modes}';

    protected $description = 'Rebuild heatmap rollups for a date range (delegates to heatmap:aggregate).';

    public function handle(): int
    {
        return $this->call('heatmap:aggregate', array_filter([
            '--from' => $this->option('from'),
            '--to' => $this->option('to'),
            '--tier' => $this->option('tier'),
            '--mode' => $this->option('mode'),
            '--all-modes' => $this->option('all-modes'),
        ], fn ($v) => $v !== null && $v !== false && $v !== ''));
    }
}
