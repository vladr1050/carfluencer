<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HeatmapAggregateDayCommand extends Command
{
    protected $signature = 'heatmap:aggregate-day
                            {day : UTC calendar day Y-m-d}
                            {--tier= : Only this zoom tier index (0-based)}
                            {--mode=driving : driving or parking}
                            {--all-modes : Both modes}';

    protected $description = 'Aggregate heatmap rollups for a single UTC day (delegates to heatmap:aggregate).';

    public function handle(): int
    {
        return $this->call('heatmap:aggregate', array_filter([
            '--day' => $this->argument('day'),
            '--tier' => $this->option('tier'),
            '--mode' => $this->option('mode'),
            '--all-modes' => $this->option('all-modes'),
        ], fn ($v) => $v !== null && $v !== false && $v !== ''));
    }
}
