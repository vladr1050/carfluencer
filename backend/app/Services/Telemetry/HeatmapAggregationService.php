<?php

namespace App\Services\Telemetry;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills heatmap_cells_daily from device_locations using the same moving/stopped rules as {@see DeviceLocationHeatmapBuckets}.
 *
 * Parking MVP: point-based classification (speed + ignition). Future: optional session-weighted rollups.
 */
final class HeatmapAggregationService
{
    public const MODE_DRIVING = 'driving';

    public const MODE_PARKING = 'parking';

    /**
     * Replace all rollup rows for (day, tier, mode) then insert from raw telemetry.
     */
    public function aggregateDayTierMode(Carbon $dayUtc, int $zoomTier, string $mode): void
    {
        if (! in_array($mode, [self::MODE_DRIVING, self::MODE_PARKING], true)) {
            throw new \InvalidArgumentException('mode must be driving or parking');
        }

        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException('Heatmap aggregation is implemented for PostgreSQL only.');
        }

        $tierCount = HeatmapBucketStrategy::tierCount();
        if ($zoomTier < 0 || $zoomTier >= $tierCount) {
            throw new \InvalidArgumentException('zoom_tier out of range');
        }

        $decimals = HeatmapBucketStrategy::decimalPlacesForTier($zoomTier);
        $threshold = (float) config('telemetry.parking_speed_kmh_max');
        $drivingMinSpeedKmh = (float) config('telemetry.heatmap.rollup.driving_min_speed_kmh', 5.0);
        $drivingMinSpeedKmh = max(0.0, $drivingMinSpeedKmh);

        $dayStr = $dayUtc->copy()->utc()->toDateString();
        $start = $dayUtc->copy()->utc()->startOfDay();
        $end = $dayUtc->copy()->utc()->addDay()->startOfDay();

        $latExpr = HeatmapBucketStrategy::pgsqlRoundLatExpr('latitude', $decimals);
        $lngExpr = HeatmapBucketStrategy::pgsqlRoundLngExpr('longitude', $decimals);

        if ($mode === self::MODE_DRIVING) {
            $whereMotion = <<<'SQL'
NOT (ignition IS NOT DISTINCT FROM false)
AND NOT (speed IS NOT NULL AND speed <= ?)
SQL;
        } else {
            $whereMotion = <<<'SQL'
(ignition IS NOT DISTINCT FROM false OR (speed IS NOT NULL AND speed <= ?))
SQL;
        }

        DB::transaction(function () use ($dayStr, $zoomTier, $mode, $start, $end, $latExpr, $lngExpr, $whereMotion, $threshold, $drivingMinSpeedKmh): void {
            DB::table('heatmap_cells_daily')
                ->where('day', $dayStr)
                ->where('zoom_tier', $zoomTier)
                ->where('mode', $mode)
                ->delete();

            $sql = <<<SQL
INSERT INTO heatmap_cells_daily (day, mode, zoom_tier, lat_bucket, lng_bucket, device_id, samples_count, weight_value, created_at, updated_at)
SELECT
    CAST(? AS date),
    ?,
    ?,
    {$latExpr},
    {$lngExpr},
    device_id,
    COUNT(*)::int,
    COUNT(*)::numeric,
    NOW(),
    NOW()
FROM device_locations
WHERE event_at >= ? AND event_at < ?
  AND ({$whereMotion})
GROUP BY device_id, {$latExpr}, {$lngExpr}
HAVING COUNT(*) > 0
SQL;

            // Bindings: day/mode/tier, event_at bounds, then speed cutoff (driving floor vs parking ceiling).
            $speedCutoff = $mode === self::MODE_DRIVING ? $drivingMinSpeedKmh : $threshold;
            DB::statement($sql, [
                $dayStr,
                $mode,
                $zoomTier,
                $start->toDateTimeString(),
                $end->toDateTimeString(),
                $speedCutoff,
            ]);
        });
    }

    /**
     * All configured tiers and both modes for one UTC calendar day.
     */
    public function aggregateDay(Carbon $dayUtc, ?int $onlyTier = null, ?string $onlyMode = null): void
    {
        $tiers = range(0, HeatmapBucketStrategy::tierCount() - 1);
        $modes = [self::MODE_DRIVING, self::MODE_PARKING];

        foreach ($tiers as $t) {
            if ($onlyTier !== null && $onlyTier !== $t) {
                continue;
            }
            foreach ($modes as $m) {
                if ($onlyMode !== null && $onlyMode !== $m) {
                    continue;
                }
                $this->aggregateDayTierMode($dayUtc, $t, $m);
            }
        }
    }

    /**
     * Inclusive date range in UTC calendar days.
     *
     * @param  string  $fromYmd  Y-m-d
     * @param  string  $toYmd  Y-m-d
     */
    public function aggregateRange(string $fromYmd, string $toYmd, ?int $onlyTier = null, ?string $onlyMode = null): void
    {
        $from = Carbon::parse($fromYmd, 'UTC')->startOfDay();
        $to = Carbon::parse($toYmd, 'UTC')->startOfDay();
        if ($to->lt($from)) {
            return;
        }

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $this->aggregateDay($d, $onlyTier, $onlyMode);
        }
    }
}
