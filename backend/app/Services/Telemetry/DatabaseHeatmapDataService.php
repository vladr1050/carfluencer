<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class DatabaseHeatmapDataService implements HeatmapDataServiceInterface
{
    public function fetchHeatmapData(int $campaignId, array $filters = []): array
    {
        $mode = $filters['mode'] ?? 'both';

        $vehicleIds = $this->heatmapVehicleIds($campaignId, $filters);

        $imeis = Vehicle::query()->whereIn('id', $vehicleIds)->pluck('imei')->filter()->values()->all();

        if ($imeis === []) {
            return [
                'points' => [],
                'buckets' => [],
                'metrics' => $this->emptyMetrics($campaignId, $mode),
            ];
        }

        $normalization = $filters['normalization'] ?? 'p95';
        if (! in_array($normalization, ['max', 'p95', 'p99'], true)) {
            $normalization = 'p95';
        }

        $q = DeviceLocation::query()->whereIn('device_id', $imeis);
        if (! empty($filters['date_from'])) {
            $q->whereDate('event_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('event_at', '<=', $filters['date_to']);
        }

        $bucketsRaw = DeviceLocationHeatmapBuckets::groupedDualCounts($q->clone());

        $gamma = TelemetryHeatmapConfig::intensityGamma();
        $wMoving = $bucketsRaw->map(fn ($r) => (int) $r->w_moving)->all();
        $wStopped = $bucketsRaw->map(fn ($r) => (int) $r->w_stopped)->all();
        $wTotals = $bucketsRaw->map(fn ($r) => (int) $r->w_moving + (int) $r->w_stopped)->all();

        $capMoving = HeatmapIntensityNormalizer::capFromWeights($wMoving, $normalization);
        $capStopped = HeatmapIntensityNormalizer::capFromWeights($wStopped, $normalization);
        $capTotal = HeatmapIntensityNormalizer::capFromWeights($wTotals, $normalization);

        $bucketsOut = [];
        $points = [];

        foreach ($bucketsRaw as $r) {
            $lat = (float) $r->lat;
            $lng = (float) $r->lng;
            $wm = (int) $r->w_moving;
            $ws = (int) $r->w_stopped;
            $wt = $wm + $ws;

            $im = HeatmapIntensityNormalizer::normalize($wm, $capMoving, $gamma);
            $is = HeatmapIntensityNormalizer::normalizeStopped($ws, $capStopped);
            $it = HeatmapIntensityNormalizer::normalize($wt, $capTotal, $gamma);

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $wt,
                'intensity_moving' => $im,
                'intensity_stopped' => $is,
                'rank_moving_pct' => HeatmapIntensityNormalizer::rankPercentBelow($wm, $wMoving),
                'rank_stopped_pct' => HeatmapIntensityNormalizer::rankPercentBelow($ws, $wStopped),
            ];

            if ($mode === 'driving') {
                if ($wm > 0) {
                    $points[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $im, 'w' => $wm, 'w_moving' => $wm, 'w_stopped' => $ws];
                }
            } elseif ($mode === 'parking') {
                if ($ws > 0) {
                    $points[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $is, 'w' => $ws, 'w_moving' => $wm, 'w_stopped' => $ws];
                }
            } elseif ($wt > 0) {
                $points[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $it, 'w' => $wt, 'w_moving' => $wm, 'w_stopped' => $ws];
            }
        }

        $metrics = $this->resolveMetrics($campaignId, $filters);
        $metrics['mode'] = $mode;
        $metrics['heatmap_motion'] = self::heatmapMotionLabel($mode);
        $metrics['campaign_id'] = $campaignId;
        $metrics['intensity_gamma'] = $gamma;
        $metrics['intensity_stopped_power'] = HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER;
        $metrics['normalization'] = $normalization;
        $metrics['cap_moving'] = $capMoving;
        $metrics['cap_stopped'] = $capStopped;
        $metrics['cap_total'] = $capTotal;

        return [
            'points' => $points,
            'buckets' => $bucketsOut,
            'metrics' => $metrics,
        ];
    }

    /**
     * Vehicle IDs attached to the campaign, optionally narrowed by heatmap filters.
     *
     * @return Collection<int, int>
     */
    private function heatmapVehicleIds(int $campaignId, array $filters): Collection
    {
        $vehicleIds = Campaign::query()
            ->findOrFail($campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id');

        if (! empty($filters['vehicle_ids'])) {
            $allowed = collect($filters['vehicle_ids'])->map('intval')->all();
            $vehicleIds = $vehicleIds->filter(fn (int $id) => in_array($id, $allowed, true));
        }

        return $vehicleIds->values();
    }

    /**
     * One streaming pass per IMEI: driving km (same rule as daily rollup) + parking minutes (consecutive “stopped” segments).
     *
     * @param  list<string>  $imeis
     * @return array{km: float, parking_minutes: float}
     */
    private function estimateDrivingAndParkingFromDeviceLocations(array $imeis, ?string $from, ?string $to): array
    {
        $maxGap = (int) config('telemetry.heatmap.max_parking_segment_seconds', 7200);
        $totalKm = 0.0;
        $totalParkMin = 0.0;

        foreach ($imeis as $imei) {
            $q = DeviceLocation::query()
                ->where('device_id', $imei)
                ->orderBy('event_at');
            if ($from) {
                $q->whereDate('event_at', '>=', $from);
            }
            if ($to) {
                $q->whereDate('event_at', '<=', $to);
            }

            $prev = null;
            foreach ($q->clone()->orderBy('id')->cursor(['id', 'latitude', 'longitude', 'speed', 'ignition']) as $p) {
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

        return ['km' => $totalKm, 'parking_minutes' => $totalParkMin];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetrics(int $campaignId, array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        $vehicleIdsArr = $this->heatmapVehicleIds($campaignId, $filters)->all();
        $imeis = Vehicle::query()->whereIn('id', $vehicleIdsArr)->pluck('imei')->filter()->values()->all();

        if ($vehicleIdsArr === [] || $imeis === []) {
            return [
                'impressions' => 0,
                'driving_distance_km' => 0.0,
                'driving_time_hours' => 0.0,
                'parking_time_hours' => 0.0,
                'data_source' => 'none',
            ];
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

        $svc = app(CampaignVehicleTelemetryService::class);
        $driveMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

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
            ];
        }

        $locQ = DeviceLocation::query()->whereIn('device_id', $imeis);
        if ($from) {
            $locQ->whereDate('event_at', '>=', $from);
        }
        if ($to) {
            $locQ->whereDate('event_at', '<=', $to);
        }
        $rawCount = $locQ->count();
        $mult = (int) config('telemetry.impression_sample_multiplier');

        $est = $this->estimateDrivingAndParkingFromDeviceLocations($imeis, $from, $to);
        $estDist = $est['km'];
        $estParkMin = $est['parking_minutes'];
        $driveMinSessionsFb = $svc->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessionsFb = $svc->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

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
            'location_samples' => $rawCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(int $campaignId, string $mode): array
    {
        return [
            'impressions' => 0,
            'driving_distance_km' => 0.0,
            'driving_time_hours' => 0.0,
            'parking_time_hours' => 0.0,
            'mode' => $mode,
            'heatmap_motion' => self::heatmapMotionLabel($mode),
            'campaign_id' => $campaignId,
            'data_source' => 'none',
            'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
            'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
            'normalization' => 'p95',
            'cap_moving' => 1,
            'cap_stopped' => 1,
            'cap_total' => 1,
        ];
    }

    private static function heatmapMotionLabel(string $mode): string
    {
        return match ($mode) {
            'parking' => 'stopped',
            'driving' => 'moving',
            default => 'both',
        };
    }
}
