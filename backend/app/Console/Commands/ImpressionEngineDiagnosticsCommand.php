<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImpressionEngineDiagnosticsCommand extends Command
{
    protected $signature = 'impression-engine:diagnostics
        {--campaign= : Campaign ID}
        {--from= : Snapshot period start Y-m-d}
        {--to= : Snapshot period end Y-m-d}';

    protected $description = 'Resolved impression-engine config, queue, failed jobs, and optional hourly exposure row counts.';

    public function handle(): int
    {
        $this->info('Environment');
        $this->line('  APP_ENV: '.config('app.env'));
        $this->line('  Config cached: '.(app()->configurationIsCached() ? 'yes — run `php artisan config:clear` after changing .env' : 'no'));

        $this->newLine();
        $this->info('impression_engine (effective config)');
        $this->line('  IMPRESSION_ENGINE_STORE_EXPOSURE_HOURLY => store_exposure_hourly: '.(
            config('impression_engine.calculation.store_exposure_hourly') ? 'true' : 'false'
        ));
        $this->line('  timezone: '.config('impression_engine.calculation.timezone'));
        $this->line('  IMPRESSION_ENGINE_H3_DRIVER => h3.driver: '.config('impression_engine.h3.driver'));
        $this->line('  h3.resolution: '.config('impression_engine.h3.resolution'));

        $this->newLine();
        $this->info('Queue');
        $this->line('  QUEUE_CONNECTION => queue.default: '.config('queue.default'));
        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
            $this->line('  failed_jobs: '.$failed.($failed > 0 ? ' — run `php artisan queue:failed`' : ''));
        } else {
            $this->line('  failed_jobs table: missing');
        }

        $this->newLine();
        $this->info('Tables');
        foreach (['campaign_impression_stats', 'campaign_vehicle_exposure_hourly', 'mobility_reference_cells', 'geo_zones'] as $table) {
            $this->line('  '.$table.': '.(Schema::hasTable($table) ? 'ok' : 'missing'));
        }

        $this->newLine();
        $this->info('Counts');
        if (Schema::hasTable('campaign_vehicle_exposure_hourly')) {
            $this->line('  campaign_vehicle_exposure_hourly rows (all): '.DB::table('campaign_vehicle_exposure_hourly')->count());
        }
        if (Schema::hasTable('geo_zones')) {
            $this->line('  geo_zones active: '.DB::table('geo_zones')->where('active', true)->count());
        }
        if (Schema::hasTable('mobility_reference_cells')) {
            $this->line('  mobility_reference_cells (all): '.DB::table('mobility_reference_cells')->count());
        }

        $campaignId = $this->option('campaign');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($campaignId !== null && $from !== null && $to !== null && Schema::hasTable('campaign_vehicle_exposure_hourly')) {
            $this->newLine();
            $this->info('Hourly exposure for campaign '.(int) $campaignId.' — '.$from.' … '.$to);
            $n = (int) DB::table('campaign_vehicle_exposure_hourly')
                ->where('campaign_id', (int) $campaignId)
                ->whereBetween('date', [$from, $to])
                ->count();
            $this->line('  rows in range: '.$n);
        } elseif ($campaignId !== null) {
            $this->newLine();
            $this->warn('Pass --from=Y-m-d and --to=Y-m-d with --campaign to count hourly rows for the snapshot period.');
        }

        $this->newLine();
        $this->info('Logs (grep on server)');
        $this->line('  tail -n 200 storage/logs/laravel.log');
        $this->line('  rg -i "CalculateCampaign|ImpressionEngine|campaign_vehicle_exposure" storage/logs');

        return self::SUCCESS;
    }
}
