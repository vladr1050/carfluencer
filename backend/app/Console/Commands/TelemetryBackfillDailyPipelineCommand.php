<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs {@see TelemetryBuildStopSessionsCommand} then {@see TelemetryAggregateDailyCommand}
 * for each UTC calendar day in an inclusive range (historical device_locations backfill).
 */
class TelemetryBackfillDailyPipelineCommand extends Command
{
    protected $signature = 'telemetry:backfill-daily-pipeline
                            {--from= : First UTC calendar day (YYYY-MM-DD), inclusive}
                            {--to= : Last UTC calendar day (YYYY-MM-DD), inclusive}
                            {--campaign= : Stop sessions for this campaign IMEIs only; daily aggregates for this campaign id only}
                            {--skip-sessions : Only run aggregate-daily (skip build-stop-sessions)}
                            {--skip-aggregate : Only run build-stop-sessions (skip aggregate-daily)}';

    protected $description = 'Run build-stop-sessions and aggregate-daily for each UTC day between --from and --to (inclusive).';

    public function handle(): int
    {
        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');
        if (! is_string($fromOpt) || $fromOpt === '' || ! is_string($toOpt) || $toOpt === '') {
            $this->error('Both --from=YYYY-MM-DD and --to=YYYY-MM-DD are required (UTC calendar days, inclusive).');

            return self::FAILURE;
        }

        try {
            $from = Carbon::parse($fromOpt, 'UTC')->startOfDay();
            $to = Carbon::parse($toOpt, 'UTC')->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid --from or --to: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $skipSessions = (bool) $this->option('skip-sessions');
        $skipAggregate = (bool) $this->option('skip-aggregate');
        if ($skipSessions && $skipAggregate) {
            $this->error('Cannot use both --skip-sessions and --skip-aggregate.');

            return self::FAILURE;
        }

        $campaignOpt = $this->option('campaign');
        $campaignArg = null;
        if (is_string($campaignOpt) && $campaignOpt !== '' && is_numeric($campaignOpt)) {
            $cid = (int) $campaignOpt;
            if (! Campaign::query()->whereKey($cid)->exists()) {
                $this->error('Campaign not found for --campaign='.$cid);

                return self::FAILURE;
            }
            $campaignArg = (string) $cid;
            $this->info("Scoped to campaign #{$cid} (stop_sessions for its IMEIs only; daily rows for this campaign only).");
        }

        $totalDays = (int) $from->diffInDays($to) + 1;
        $this->info("Backfill {$totalDays} UTC day(s): {$from->toDateString()} … {$to->toDateString()}.");

        $current = $from->copy();
        $i = 0;
        while ($current->lte($to)) {
            $i++;
            $d = $current->toDateString();
            $this->line("[{$i}/{$totalDays}] {$d}");

            if (! $skipSessions) {
                $params = ['--date' => $d];
                if ($campaignArg !== null) {
                    $params['--campaign'] = $campaignArg;
                }
                Artisan::call('telemetry:build-stop-sessions', $params);
                $out = trim(Artisan::output());
                if ($out !== '') {
                    $this->line($out);
                }
            }
            if (! $skipAggregate) {
                $params = ['--date' => $d];
                if ($campaignArg !== null) {
                    $params['--campaign'] = $campaignArg;
                }
                Artisan::call('telemetry:aggregate-daily', $params);
                $out = trim(Artisan::output());
                if ($out !== '') {
                    $this->line($out);
                }
            }

            $current->addDay();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
