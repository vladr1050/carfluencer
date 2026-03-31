<?php

namespace App\Console\Commands;

use App\Models\CampaignImpressionStat;
use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use App\Services\ImpressionEngine\LibH3Indexer;
use App\Services\ImpressionEngine\MobilitySpatialIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImpressionEngineDiagnosticsCommand extends Command
{
    protected $signature = 'impression-engine:diagnostics
        {--snapshot= : campaign_impression_stats.id (sets campaign + dates + mobility version)}
        {--campaign= : Campaign ID}
        {--from= : Snapshot period start Y-m-d}
        {--to= : Snapshot period end Y-m-d}
        {--mobility-version= : Required for mobility probe without --snapshot}';

    protected $description = 'Resolved impression-engine config, queue, failed jobs, hourly counts, H3 decode, exposure stats, and mobility resolution probe.';

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

        $snapshotId = $this->option('snapshot');
        $campaignId = $this->option('campaign');
        $from = $this->option('from');
        $to = $this->option('to');
        $mobilityVersionOption = $this->option('mobility-version');

        /** @var CampaignImpressionStat|null $resolvedStat */
        $resolvedStat = null;

        if ($snapshotId !== null) {
            $resolvedStat = CampaignImpressionStat::query()->find((int) $snapshotId);
            if ($resolvedStat === null) {
                $this->error('campaign_impression_stats id not found: '.$snapshotId);

                return self::FAILURE;
            }
            $campaignId = (string) $resolvedStat->campaign_id;
            $from = $resolvedStat->date_from->toDateString();
            $to = $resolvedStat->date_to->toDateString();
            $this->newLine();
            $this->info('Resolved from snapshot #'.$snapshotId);
            $this->line('  campaign_id: '.$campaignId);
            $this->line('  date_from / date_to: '.$from.' … '.$to);
            $this->line('  mobility_data_version: '.$resolvedStat->mobility_data_version);
            $this->line('  coefficients_version: '.$resolvedStat->coefficients_version);
        }

        $mobilityVersionForProbe = $resolvedStat?->mobility_data_version
            ?? (is_string($mobilityVersionOption) && $mobilityVersionOption !== '' ? $mobilityVersionOption : null);

        if ($campaignId !== null && $from !== null && $to !== null && Schema::hasTable('campaign_vehicle_exposure_hourly')) {
            $this->newLine();
            $this->info('Hourly exposure for campaign '.(int) $campaignId.' — '.$from.' … '.$to);
            $n = (int) DB::table('campaign_vehicle_exposure_hourly')
                ->where('campaign_id', (int) $campaignId)
                ->whereBetween('date', [$from, $to])
                ->count();
            $this->line('  rows in range: '.$n);

            $exp = DB::table('campaign_vehicle_exposure_hourly')
                ->where('campaign_id', (int) $campaignId)
                ->whereBetween('date', [$from, $to])
                ->selectRaw(
                    'SUM(CASE WHEN exposure_seconds = 0 THEN 1 ELSE 0 END) as zero_exp_rows, '.
                    'MIN(exposure_seconds) as min_exp, MAX(exposure_seconds) as max_exp, '.
                    'SUM(exposure_seconds) as sum_exp'
                )
                ->first();
            if ($exp !== null) {
                $this->line('  exposure_seconds: min='.(int) $exp->min_exp.' max='.(int) $exp->max_exp.' sum='.(int) $exp->sum_exp);
                $this->line('  rows with exposure_seconds=0: '.(int) $exp->zero_exp_rows);
            }

            if ($n > 0 && config('impression_engine.h3.driver') !== 'fake') {
                $sample = DB::table('campaign_vehicle_exposure_hourly')
                    ->where('campaign_id', (int) $campaignId)
                    ->whereBetween('date', [$from, $to])
                    ->value('cell_id');
                if ($sample !== null) {
                    try {
                        $geo = app(H3IndexerInterface::class)->cellIdToLatLng((string) $sample);
                        $this->line('  H3 decode sample (first cell_id): lat='.round($geo['lat'], 6).' lng='.round($geo['lng'], 6));
                    } catch (Throwable $e) {
                        $this->warn('  H3 decode sample failed: '.$e->getMessage());
                    }
                }
            }

            if ($mobilityVersionForProbe !== null && $n > 0 && config('impression_engine.h3.driver') !== 'fake') {
                $this->printMobilityResolutionProbe((int) $campaignId, $from, $to, $mobilityVersionForProbe);
            } elseif ($mobilityVersionForProbe === null && $n > 0) {
                $this->newLine();
                $this->warn('  Mobility probe skipped — pass --snapshot=ID or --mobility-version=riga_v1_2025');
            }
        } elseif ($campaignId !== null) {
            $this->newLine();
            $this->warn('Pass --from=Y-m-d and --to=Y-m-d with --campaign (or use --snapshot=ID).');
        }

        $this->newLine();
        $this->info('Logs (grep on server)');
        $this->line('  tail -n 200 storage/logs/laravel.log');
        $this->line('  rg -i "CalculateCampaign|ImpressionEngine|campaign_vehicle_exposure" storage/logs');

        return self::SUCCESS;
    }

    private function printMobilityResolutionProbe(int $campaignId, string $from, string $to, string $mobilityDataVersion): void
    {
        $this->newLine();
        $this->info('Mobility resolution probe (matches zone-breakdown logic, first 5000 rows)');

        $mobilityRows = MobilityReferenceCell::query()
            ->where('data_version', $mobilityDataVersion)
            ->get();

        if ($mobilityRows->isEmpty()) {
            $this->warn('  No mobility_reference_cells for data_version ['.$mobilityDataVersion.']');

            return;
        }

        /** @var array<string, true> $direct */
        $direct = [];
        foreach ($mobilityRows as $c) {
            $direct[LibH3Indexer::normalizeCellIdForIndex((string) $c->cell_id)] = true;
        }

        $spatial = new MobilitySpatialIndex($mobilityRows);
        $maxM = (float) config('impression_engine.calculation.mobility_fallback_max_meters', 300);
        $h3 = app(H3IndexerInterface::class);

        $stats = [
            'sampled' => 0,
            'zero_exposure' => 0,
            'direct_mobility' => 0,
            'fallback_mobility' => 0,
            'no_mobility' => 0,
            'h3_exception' => 0,
        ];

        $rows = DB::table('campaign_vehicle_exposure_hourly')
            ->where('campaign_id', $campaignId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('id')
            ->limit(5000)
            ->get();

        foreach ($rows as $row) {
            $stats['sampled']++;
            if ((int) $row->exposure_seconds === 0) {
                $stats['zero_exposure']++;
            }

            $cellId = LibH3Indexer::normalizeCellIdForIndex((string) $row->cell_id);
            if (isset($direct[$cellId])) {
                $stats['direct_mobility']++;

                continue;
            }

            try {
                $geo = $h3->cellIdToLatLng($cellId);
                $near = $spatial->nearestWithin($geo['lat'], $geo['lng'], $maxM);
                if ($near !== null) {
                    $stats['fallback_mobility']++;
                } else {
                    $stats['no_mobility']++;
                }
            } catch (Throwable) {
                $stats['h3_exception']++;
            }
        }

        foreach ($stats as $label => $value) {
            $this->line('  '.$label.': '.$value);
        }

        if ($stats['no_mobility'] > $stats['sampled'] / 2) {
            $this->warn('  Many rows have no mobility match — increase IMPRESSION_ENGINE_MOBILITY_FALLBACK_M or re-import mobility for this data_version.');
        }
        if ((int) $stats['zero_exposure'] === (int) $stats['sampled']) {
            $this->warn('  All sampled rows have exposure_seconds=0 — zone breakdown totals stay 0 (check hourly persist / telemetry).');
        }
    }
}
