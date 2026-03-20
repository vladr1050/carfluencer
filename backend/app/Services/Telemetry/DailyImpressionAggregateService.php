<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DailyZoneImpression;
use App\Models\DeviceLocation;
use App\Models\StopSession;
use App\Models\Vehicle;
use Carbon\CarbonInterface;

class DailyImpressionAggregateService
{
    public function aggregateForDate(CarbonInterface $date): void
    {
        $d = $date->toDateString();
        DailyImpression::query()->whereDate('stat_date', $d)->delete();
        DailyZoneImpression::query()->whereDate('stat_date', $d)->delete();

        $multiplier = (int) config('telemetry.impression_sample_multiplier');

        $campaigns = Campaign::query()
            ->where(function ($q) use ($d): void {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $d);
            })
            ->where(function ($q) use ($d): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            })
            ->with(['campaignVehicles.vehicle'])
            ->get();

        foreach ($campaigns as $campaign) {
            foreach ($campaign->campaignVehicles as $cv) {
                $vehicle = $cv->vehicle;
                if ($vehicle === null) {
                    continue;
                }
                $imei = $vehicle->imei;
                $locCount = DeviceLocation::query()
                    ->where('device_id', $imei)
                    ->whereDate('event_at', $d)
                    ->count();
                $impressions = max(0, $locCount * $multiplier);

                $parkingMinutes = (int) round(
                    StopSession::query()
                        ->where('device_id', $imei)
                        ->where('kind', 'parking')
                        ->whereDate('started_at', $d)
                        ->get()
                        ->sum(fn (StopSession $s) => $s->started_at->diffInSeconds($s->ended_at) / 60)
                );

                $drivingKm = $this->estimateDrivingDistanceKm($imei, $date);

                DailyImpression::query()->updateOrCreate(
                    [
                        'stat_date' => $d,
                        'campaign_id' => $campaign->id,
                        'vehicle_id' => $vehicle->id,
                    ],
                    [
                        'impressions' => $impressions,
                        'driving_distance_km' => $drivingKm > 0 ? round($drivingKm, 3) : null,
                        'parking_minutes' => $parkingMinutes > 0 ? $parkingMinutes : null,
                    ]
                );
            }
        }

        $this->rollupZoneImpressions($d);
    }

    private function estimateDrivingDistanceKm(string $imei, CarbonInterface $date): float
    {
        $points = DeviceLocation::query()
            ->where('device_id', $imei)
            ->whereDate('event_at', $date->toDateString())
            ->orderBy('event_at')
            ->get(['latitude', 'longitude', 'speed']);

        $sum = 0.0;
        $prev = null;
        foreach ($points as $p) {
            if ($prev !== null) {
                $sp = $p->speed !== null ? (float) $p->speed : null;
                if ($sp === null || $sp > 5) {
                    $sum += GeoMath::haversineKm(
                        (float) $prev->latitude,
                        (float) $prev->longitude,
                        (float) $p->latitude,
                        (float) $p->longitude
                    );
                }
            }
            $prev = $p;
        }

        return $sum;
    }

    private function rollupZoneImpressions(string $d): void
    {
        $sessions = StopSession::query()
            ->where('kind', 'parking')
            ->whereDate('started_at', $d)
            ->with('zones')
            ->get();

        $buckets = [];

        foreach ($sessions as $session) {
            $vehicle = Vehicle::query()->where('imei', $session->device_id)->first();
            if ($vehicle === null) {
                continue;
            }

            $campaignIds = $vehicle->campaigns()
                ->where(function ($q) use ($d): void {
                    $q->whereNull('campaigns.start_date')->orWhereDate('campaigns.start_date', '<=', $d);
                })
                ->where(function ($q) use ($d): void {
                    $q->whereNull('campaigns.end_date')->orWhereDate('campaigns.end_date', '>=', $d);
                })
                ->pluck('campaigns.id');

            foreach ($session->zones as $zone) {
                foreach ($campaignIds as $cid) {
                    $key = $zone->id.':'.$cid;
                    $buckets[$key] = ($buckets[$key] ?? 0) + $session->point_count;
                }
            }
        }

        foreach ($buckets as $key => $impr) {
            [$zoneId, $campaignId] = array_map('intval', explode(':', $key, 2));
            DailyZoneImpression::query()->updateOrCreate(
                [
                    'stat_date' => $d,
                    'zone_id' => $zoneId,
                    'campaign_id' => $campaignId,
                ],
                ['impressions' => $impr]
            );
        }
    }
}
