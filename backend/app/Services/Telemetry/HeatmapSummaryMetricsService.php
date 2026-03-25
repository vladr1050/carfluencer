<?php

namespace App\Services\Telemetry;

use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\Vehicle;

/**
 * Period-level KPIs for the advertiser heatmap: campaign + vehicles + date range only.
 * Ignores map mode (driving/parking layer), bbox, and zoom.
 */
class HeatmapSummaryMetricsService
{
    public function __construct(
        private readonly CampaignVehicleTelemetryService $stopSessions,
    ) {}

    /**
     * @return array{
     *     impressions: int|null,
     *     driving_distance_km: float|null,
     *     driving_time_hours: float|null,
     *     parking_time_hours: float|null,
     *     data_source: string,
     *     is_estimated: bool
     * }
     */
    public function fetchForAdvertiser(HeatmapPageQuery $query): array
    {
        $campaignId = $query->campaignId;
        $from = $query->dateFrom;
        $to = $query->dateTo;

        $vehicleIdsArr = $query->resolveCampaignVehicleIds()->all();
        $imeis = Vehicle::query()->whereIn('id', $vehicleIdsArr)->pluck('imei')->filter()->values()->all();

        if ($vehicleIdsArr === [] || $imeis === []) {
            return $this->emptySummaryNone();
        }

        $q = DailyImpression::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('vehicle_id', $vehicleIdsArr);
        if ($from) {
            $q->whereDate('stat_date', '>=', $from);
        }
        if ($to) {
            $q->whereDate('stat_date', '<=', $to);
        }

        $impr = (int) $q->sum('impressions');
        $dist = (float) $q->sum('driving_distance_km');
        $parkMin = (int) $q->sum('parking_minutes');

        $driveMinSessions = $this->stopSessions->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessions = $this->stopSessions->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

        if ($impr > 0 || $dist > 0 || $parkMin > 0 || $driveMinSessions > 0 || $parkMinSessions > 0) {
            $needsKmEst = $dist <= 0.0 && ($impr > 0 || $driveMinSessions > 0 || $parkMinSessions > 0);
            $needsParkEst = $parkMin <= 0 && $parkMinSessions <= 0
                && ($impr > 0 || $dist > 0 || $driveMinSessions > 0 || $parkMinSessions > 0 || $needsKmEst);

            $estimatedDist = 0.0;
            $estimatedParkMin = 0.0;
            if ($needsKmEst || $needsParkEst) {
                $est = $this->estimateDrivingAndParkingFromDeviceLocations($imeis, $from, $to);
                if ($needsKmEst) {
                    $estimatedDist = $est['km'];
                }
                if ($needsParkEst) {
                    $estimatedParkMin = $est['parking_minutes'];
                }
            }

            $effectiveDist = $dist > 0 ? $dist : $estimatedDist;

            $drivingHours = $driveMinSessions > 0
                ? round($driveMinSessions / 60, 1)
                : ($effectiveDist > 0 ? round($effectiveDist / 35, 1) : 0.0);
            $parkingHours = $parkMin > 0
                ? round($parkMin / 60, 1)
                : ($parkMinSessions > 0
                    ? round($parkMinSessions / 60, 1)
                    : ($estimatedParkMin > 0 ? round($estimatedParkMin / 60, 1) : 0.0));

            $usedGpsEst = ($dist <= 0.0 && $estimatedDist > 0.0)
                || ($parkMin <= 0 && $parkMinSessions <= 0 && $estimatedParkMin > 0.0);
            $dataSource = $usedGpsEst ? 'daily_impressions_estimated' : 'daily_impressions';

            return [
                'impressions' => $impr,
                'driving_distance_km' => round($effectiveDist, 2),
                'driving_time_hours' => $drivingHours,
                'parking_time_hours' => $parkingHours,
                'data_source' => $dataSource,
                'is_estimated' => $usedGpsEst,
            ];
        }

        if (filter_var(config('telemetry.heatmap.rollup.advertiser_raw_summary_fallback', false), FILTER_VALIDATE_BOOLEAN)) {
            return $this->rawDeviceLocationsFallback($campaignId, $from, $to, $vehicleIdsArr, $imeis);
        }

        return [
            'impressions' => null,
            'driving_distance_km' => null,
            'driving_time_hours' => null,
            'parking_time_hours' => null,
            'data_source' => 'insufficient_aggregates',
            'is_estimated' => false,
        ];
    }

    /**
     * @param  list<int>  $vehicleIdsArr
     * @param  list<string>  $imeis
     * @return array{
     *     impressions: int|null,
     *     driving_distance_km: float|null,
     *     driving_time_hours: float|null,
     *     parking_time_hours: float|null,
     *     data_source: string,
     *     is_estimated: bool
     * }
     */
    private function rawDeviceLocationsFallback(
        int $campaignId,
        ?string $from,
        ?string $to,
        array $vehicleIdsArr,
        array $imeis
    ): array {
        $mult = (int) config('telemetry.impression_sample_multiplier');

        $scan = $this->scanDeviceLocationsDrivingParkingMetrics($imeis, $from, $to, true);
        $rawCount = (int) ($scan['row_count'] ?? 0);
        $estDist = $scan['km'];
        $estParkMin = $scan['parking_minutes'];
        $driveMinSessionsFb = $this->stopSessions->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessionsFb = $this->stopSessions->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

        $drivingHoursFb = $driveMinSessionsFb > 0
            ? round($driveMinSessionsFb / 60, 1)
            : ($estDist > 0 ? round($estDist / 35, 1) : 0.0);
        $parkingHoursFb = $parkMinSessionsFb > 0
            ? round($parkMinSessionsFb / 60, 1)
            : ($estParkMin > 0 ? round($estParkMin / 60, 1) : 0.0);

        $hasTelemetry = $estDist > 0.0 || $estParkMin > 0.0 || $driveMinSessionsFb > 0 || $parkMinSessionsFb > 0;

        return [
            'impressions' => $rawCount * $mult,
            'driving_distance_km' => round($estDist, 2),
            'driving_time_hours' => $drivingHoursFb,
            'parking_time_hours' => $parkingHoursFb,
            'data_source' => $hasTelemetry ? 'device_locations_estimated' : 'device_locations_raw',
            'is_estimated' => true,
        ];
    }

    /**
     * @return array{
     *     impressions: int|null,
     *     driving_distance_km: float|null,
     *     driving_time_hours: float|null,
     *     parking_time_hours: float|null,
     *     data_source: string,
     *     is_estimated: bool
     * }
     */
    private function emptySummaryNone(): array
    {
        return [
            'impressions' => 0,
            'driving_distance_km' => 0.0,
            'driving_time_hours' => 0.0,
            'parking_time_hours' => 0.0,
            'data_source' => 'none',
            'is_estimated' => false,
        ];
    }

    /**
     * @param  list<string>  $imeis
     * @return array{km: float, parking_minutes: float, row_count?: int}
     */
    private function scanDeviceLocationsDrivingParkingMetrics(array $imeis, ?string $from, ?string $to, bool $countRows): array
    {
        $maxGap = (int) config('telemetry.heatmap.max_parking_segment_seconds', 7200);
        $totalKm = 0.0;
        $totalParkMin = 0.0;
        $rows = 0;

        foreach ($imeis as $imei) {
            $q = DeviceLocation::query()
                ->where('device_id', $imei)
                ->orderBy('event_at');
            DeviceLocationEventAtRange::apply($q, $from, $to);

            $prev = null;
            foreach ($q->clone()->orderBy('id')->cursor(['id', 'latitude', 'longitude', 'speed', 'ignition', 'event_at']) as $p) {
                if ($countRows) {
                    $rows++;
                }
                if ($prev !== null) {
                    $secs = max(0, $prev->event_at->diffInSeconds($p->event_at));
                    if ($secs > 0 && $secs <= $maxGap
                        && DeviceLocationMotionScope::isParkingState($prev->ignition, $prev->speed)
                        && DeviceLocationMotionScope::isParkingState($p->ignition, $p->speed)) {
                        $totalParkMin += $secs / 60.0;
                    }

                    $sp = $p->speed !== null ? (float) $p->speed : null;
                    if ($sp === null || $sp > 5) {
                        $totalKm += GeoMath::haversineKm(
                            (float) $prev->latitude,
                            (float) $prev->longitude,
                            (float) $p->latitude,
                            (float) $p->longitude
                        );
                    }
                }
                $prev = $p;
            }
        }

        $out = ['km' => $totalKm, 'parking_minutes' => $totalParkMin];
        if ($countRows) {
            $out['row_count'] = $rows;
        }

        return $out;
    }

    /**
     * @param  list<string>  $imeis
     * @return array{km: float, parking_minutes: float}
     */
    private function estimateDrivingAndParkingFromDeviceLocations(array $imeis, ?string $from, ?string $to): array
    {
        $r = $this->scanDeviceLocationsDrivingParkingMetrics($imeis, $from, $to, false);

        return ['km' => $r['km'], 'parking_minutes' => $r['parking_minutes']];
    }
}
