<?php

namespace App\Services\ImpressionEngine;

use App\Jobs\BuildCampaignImpressionZoneBreakdownJob;
use App\Models\CampaignImpressionStat;
use App\Models\CampaignVehicleExposureHourly;
use App\Models\GeoZone;
use App\Models\ImpressionCoefficient;
use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Estimates impression contribution per active {@see GeoZone} for a completed snapshot,
 * using stored hourly exposure rows and the same formula as {@see CampaignImpressionCalculationService}.
 *
 * @phpstan-type ZoneRow array{code: string, name: string, impressions: int, share_pct: float}
 */
final class CampaignImpressionGeoZoneBreakdownService
{
    public function __construct(
        private readonly H3IndexerInterface $h3,
    ) {}

    /**
     * For Filament/PDF: use persisted breakdown when present; avoid synchronous full scans on huge snapshots.
     *
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     note: string|null,
     *     total_impressions: int,
     *     top_zones: list<ZoneRow>,
     *     unattributed_impressions: int,
     *     unattributed_share_pct: float
     * }
     */
    public function breakdownForSnapshot(CampaignImpressionStat $stat, int $limit = 10): array
    {
        $cached = $stat->zone_breakdown_json;
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $from = $stat->date_from->toDateString();
        $to = $stat->date_to->toDateString();
        $rowCount = CampaignVehicleExposureHourly::query()
            ->where('campaign_id', $stat->campaign_id)
            ->whereBetween('date', [$from, $to])
            ->count();

        $maxLive = (int) config('impression_engine.calculation.zone_breakdown_max_hourly_rows_live', 25_000);

        if ($rowCount > $maxLive) {
            if ($stat->status === CampaignImpressionStat::STATUS_DONE) {
                BuildCampaignImpressionZoneBreakdownJob::dispatch($stat->id);
            }

            return [
                'available' => false,
                'reason' => 'This snapshot has many hourly exposure rows; the top‑10 zone breakdown is computed in the background (several minutes). Refresh this page after a short wait.',
                'note' => null,
                'total_impressions' => 0,
                'top_zones' => [],
                'unattributed_impressions' => 0,
                'unattributed_share_pct' => 0.0,
            ];
        }

        $live = $this->topZonesForSnapshot($stat, $limit);

        if ($stat->status === CampaignImpressionStat::STATUS_DONE && ($live['available'] ?? false)) {
            $stat->update(['zone_breakdown_json' => $live]);
        }

        return $live;
    }

    /**
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     note: string|null,
     *     total_impressions: int,
     *     top_zones: list<ZoneRow>,
     *     unattributed_impressions: int,
     *     unattributed_share_pct: float
     * }
     */
    public function topZonesForSnapshot(CampaignImpressionStat $stat, int $limit = 10): array
    {
        if ($stat->status !== CampaignImpressionStat::STATUS_DONE) {
            return [
                'available' => false,
                'reason' => 'Top zones are available after the snapshot status is Done.',
                'note' => null,
                'total_impressions' => 0,
                'top_zones' => [],
                'unattributed_impressions' => 0,
                'unattributed_share_pct' => 0.0,
            ];
        }

        $coeff = ImpressionCoefficient::query()->where('version', $stat->coefficients_version)->first();
        if ($coeff === null) {
            return [
                'available' => false,
                'reason' => 'Coefficient version not found for this snapshot.',
                'note' => null,
                'total_impressions' => 0,
                'top_zones' => [],
                'unattributed_impressions' => 0,
                'unattributed_share_pct' => 0.0,
            ];
        }

        $mobilityRows = MobilityReferenceCell::query()
            ->where('data_version', $stat->mobility_data_version)
            ->get();

        if ($mobilityRows->isEmpty()) {
            return [
                'available' => false,
                'reason' => 'No mobility data for this snapshot version.',
                'note' => null,
                'total_impressions' => 0,
                'top_zones' => [],
                'unattributed_impressions' => 0,
                'unattributed_share_pct' => 0.0,
            ];
        }

        /** @var array<string, MobilityReferenceCell> $direct */
        $direct = [];
        foreach ($mobilityRows as $c) {
            $direct[LibH3Indexer::normalizeCellIdForIndex((string) $c->cell_id)] = $c;
        }
        $spatial = new MobilitySpatialIndex($mobilityRows);
        $maxM = (float) config('impression_engine.calculation.mobility_fallback_max_meters', 300);

        $zones = GeoZone::query()->where('active', true)->orderBy('code')->get();

        /** @var array<int|string, float> $sums */
        $sums = [];
        $unattributed = 0.0;

        $from = $stat->date_from->toDateString();
        $to = $stat->date_to->toDateString();

        $hourlyRowCount = CampaignVehicleExposureHourly::query()
            ->where('campaign_id', $stat->campaign_id)
            ->whereBetween('date', [$from, $to])
            ->count();

        CampaignVehicleExposureHourly::query()
            ->where('campaign_id', $stat->campaign_id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('id')
            ->chunkById(4000, function ($chunk) use (
                &$sums,
                &$unattributed,
                $direct,
                $spatial,
                $maxM,
                $coeff,
                $zones,
            ): void {
                foreach ($chunk as $row) {
                    try {
                        $cellId = LibH3Indexer::normalizeCellIdForIndex((string) $row->cell_id);
                        $hour = (int) $row->hour;
                        $exposureSeconds = (int) $row->exposure_seconds;
                        $mode = (string) $row->mode;
                        $avgSpeed = (float) ($row->avg_vehicle_speed ?? 0.0);

                        $mobilityArr = null;
                        /** @var float|null $zoneLat */
                        $zoneLat = null;
                        /** @var float|null $zoneLng */
                        $zoneLng = null;

                        if (isset($direct[$cellId])) {
                            /** @var MobilityReferenceCell $c */
                            $c = $direct[$cellId];
                            $mobilityArr = [
                                'vehicle_aadt' => $c->vehicle_aadt,
                                'pedestrian_daily' => $c->pedestrian_daily,
                                'hourly_peak_factor' => $c->hourly_peak_factor,
                            ];
                            $latC = (float) $c->lat_center;
                            $lngC = (float) $c->lng_center;
                            if (abs($latC) > 1e-9 || abs($lngC) > 1e-9) {
                                $zoneLat = $latC;
                                $zoneLng = $lngC;
                            }
                        } else {
                            $geo = $this->h3->cellIdToLatLng($cellId);
                            $near = $spatial->nearestWithin($geo['lat'], $geo['lng'], $maxM);
                            if ($near !== null) {
                                $mobilityArr = $near['row'];
                                $zoneLat = $geo['lat'];
                                $zoneLng = $geo['lng'];
                            }
                        }

                        if ($mobilityArr === null) {
                            continue;
                        }

                        if ($zoneLat === null || $zoneLng === null) {
                            $geo = $this->h3->cellIdToLatLng($cellId);
                            $zoneLat = $geo['lat'];
                            $zoneLng = $geo['lng'];
                        }

                        if ($mode === 'driving') {
                            $imp = ImpressionFormula::drivingImpressions(
                                $exposureSeconds,
                                $hour,
                                $avgSpeed,
                                $mobilityArr,
                                $coeff
                            );
                        } else {
                            $imp = ImpressionFormula::parkingImpressions(
                                $exposureSeconds,
                                $hour,
                                $mobilityArr,
                                $coeff
                            );
                        }

                        $zoneKey = $this->firstZoneContaining($zones, $zoneLat, $zoneLng);
                        if ($zoneKey === null) {
                            $unattributed += $imp;
                        } else {
                            $sums[$zoneKey] = ($sums[$zoneKey] ?? 0.0) + $imp;
                        }
                    } catch (Throwable $e) {
                        report($e);

                        continue;
                    }
                }
            });

        $rounded = [];
        foreach ($sums as $key => $v) {
            $rounded[$key] = (int) round($v);
        }
        $unattributedInt = (int) round($unattributed);

        $totalBreakdown = array_sum($rounded) + $unattributedInt;
        $note = null;
        if ($totalBreakdown === 0 && (int) $stat->total_gross_impressions > 0) {
            if ($hourlyRowCount === 0) {
                $note = 'No hourly exposure rows for this period. Set IMPRESSION_ENGINE_STORE_EXPOSURE_HOURLY=true, deploy the latest engine code, then queue calculation again for the same campaign and dates. Run php artisan config:clear on the server if env was changed.';
            } else {
                $note = 'Hourly exposure rows exist ('.$hourlyRowCount.') but attributed impressions are zero — check mobility version matches imported cells, H3/cell_id alignment, and that active Geo zones cover these locations.';
            }
        }

        arsort($rounded, SORT_NUMERIC);
        $top = [];
        $i = 0;
        foreach ($rounded as $zoneKey => $impr) {
            if ($i >= $limit) {
                break;
            }
            /** @var GeoZone|null $z */
            $z = $zones->firstWhere('id', $zoneKey);
            if ($z === null) {
                continue;
            }
            $top[] = [
                'code' => (string) $z->code,
                'name' => (string) $z->name,
                'impressions' => $impr,
                'share_pct' => 0.0,
            ];
            $i++;
        }

        $denom = $totalBreakdown > 0 ? $totalBreakdown : (int) $stat->total_gross_impressions;

        if ($denom > 0) {
            foreach ($top as $idx => $row) {
                $top[$idx]['share_pct'] = round(100.0 * $row['impressions'] / $denom, 2);
            }
        }

        $unattributedPct = $denom > 0 ? round(100.0 * $unattributedInt / $denom, 2) : 0.0;

        return [
            'available' => true,
            'reason' => null,
            'note' => $note,
            'total_impressions' => $totalBreakdown > 0 ? $totalBreakdown : (int) $stat->total_gross_impressions,
            'top_zones' => $top,
            'unattributed_impressions' => $unattributedInt,
            'unattributed_share_pct' => $unattributedPct,
        ];
    }

    /**
     * @param  Collection<int, GeoZone>  $zones
     */
    private function firstZoneContaining($zones, float $lat, float $lng): ?int
    {
        foreach ($zones as $zone) {
            if ($lat < (float) $zone->min_lat || $lat > (float) $zone->max_lat
                || $lng < (float) $zone->min_lng || $lng > (float) $zone->max_lng) {
                continue;
            }
            if ($zone->containsPoint($lat, $lng)) {
                return (int) $zone->id;
            }
        }

        return null;
    }
}
