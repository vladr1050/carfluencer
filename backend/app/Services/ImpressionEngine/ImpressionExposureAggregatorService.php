<?php

namespace App\Services\ImpressionEngine;

use App\Models\Campaign;
use App\Models\CampaignVehicleExposureHourly;
use App\Models\DeviceLocation;
use App\Models\Vehicle;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates raw device_locations into hourly buckets per campaign vehicle × H3 × mode.
 *
 * @phpstan-type Bucket array{
 *     vehicle_id: int,
 *     date: string,
 *     hour: int,
 *     cell_id: string,
 *     mode: string,
 *     point_count: int,
 *     sum_speed: float,
 * }
 */
final class ImpressionExposureAggregatorService
{
    public function __construct(
        private readonly H3IndexerInterface $h3,
    ) {}

    /**
     * @return list<Bucket>
     */
    public function aggregate(
        Campaign $campaign,
        string $dateFrom,
        string $dateTo,
        int $samplingSeconds,
    ): array {
        $tz = (string) config('impression_engine.calculation.timezone', 'Europe/Riga');
        $threshold = (float) config('impression_engine.calculation.driving_speed_threshold_kmh', 5.0);

        $vehicleRows = Vehicle::query()
            ->select(['vehicles.id', 'vehicles.imei'])
            ->join('campaign_vehicles', 'campaign_vehicles.vehicle_id', '=', 'vehicles.id')
            ->where('campaign_vehicles.campaign_id', $campaign->id)
            ->whereNotNull('vehicles.imei')
            ->get();

        $imeiToVehicle = [];
        foreach ($vehicleRows as $v) {
            $imei = preg_replace('/\D+/', '', (string) $v->imei) ?? '';
            if ($imei !== '') {
                $imeiToVehicle[$imei] = (int) $v->id;
            }
        }

        if ($imeiToVehicle === []) {
            return [];
        }

        $startLocal = CarbonImmutable::parse($dateFrom, $tz)->startOfDay();
        $endLocal = CarbonImmutable::parse($dateTo, $tz)->endOfDay();
        $startUtc = $startLocal->utc();
        $endUtc = $endLocal->utc();

        /** @var array<string, Bucket> $map */
        $map = [];

        DeviceLocation::query()
            ->whereIn('device_id', array_keys($imeiToVehicle))
            ->whereBetween('event_at', [$startUtc, $endUtc])
            ->orderBy('id')
            ->chunkById(4000, function ($chunk) use (&$map, $imeiToVehicle, $tz, $threshold, $campaign): void {
                foreach ($chunk as $loc) {
                    $deviceId = (string) $loc->device_id;
                    $vehicleId = $imeiToVehicle[$deviceId] ?? null;
                    if ($vehicleId === null) {
                        continue;
                    }
                    $lat = (float) $loc->latitude;
                    $lng = (float) $loc->longitude;
                    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                        continue;
                    }
                    $local = $loc->event_at->copy()->timezone($tz);
                    $date = $local->format('Y-m-d');
                    $hour = (int) $local->format('G');
                    $speed = (float) ($loc->speed ?? 0);
                    $mode = $speed > $threshold ? 'driving' : 'parking';
                    try {
                        $cellId = $this->h3->latLngToCellId($lat, $lng);
                    } catch (\Throwable) {
                        continue;
                    }
                    $key = $campaign->id.'|'.$vehicleId.'|'.$date.'|'.$hour.'|'.$cellId.'|'.$mode;
                    if (! isset($map[$key])) {
                        $map[$key] = [
                            'vehicle_id' => $vehicleId,
                            'date' => $date,
                            'hour' => $hour,
                            'cell_id' => $cellId,
                            'mode' => $mode,
                            'point_count' => 0,
                            'sum_speed' => 0.0,
                        ];
                    }
                    $map[$key]['point_count']++;
                    $map[$key]['sum_speed'] += $speed;
                }
            });

        $out = [];
        foreach ($map as $b) {
            $out[] = $b;
        }

        return $out;
    }

    /**
     * @param  list<Bucket>  $buckets
     */
    public function persist(Campaign $campaign, string $dateFrom, string $dateTo, array $buckets, int $samplingSeconds): void
    {
        if ($buckets === []) {
            // Do not delete existing hourly rows when there is nothing to write (e.g. idempotent
            // re-run with no telemetry in range would otherwise wipe previously stored exposure).
            return;
        }

        $tz = (string) config('impression_engine.calculation.timezone', 'Europe/Riga');
        $startLocal = CarbonImmutable::parse($dateFrom, $tz)->toDateString();
        $endLocal = CarbonImmutable::parse($dateTo, $tz)->toDateString();

        DB::transaction(function () use ($campaign, $startLocal, $endLocal, $buckets, $samplingSeconds): void {
            CampaignVehicleExposureHourly::query()
                ->where('campaign_id', $campaign->id)
                ->whereBetween('date', [$startLocal, $endLocal])
                ->delete();

            $now = now();
            $batch = [];
            foreach ($buckets as $b) {
                $exp = $b['point_count'] * $samplingSeconds;
                $avgSpeed = $b['point_count'] > 0 ? round($b['sum_speed'] / $b['point_count'], 2) : null;
                $batch[] = [
                    'campaign_id' => $campaign->id,
                    'vehicle_id' => $b['vehicle_id'],
                    'date' => $b['date'],
                    'hour' => $b['hour'],
                    'cell_id' => $b['cell_id'],
                    'mode' => $b['mode'],
                    'exposure_seconds' => $exp,
                    'avg_vehicle_speed' => $avgSpeed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= 500) {
                    DB::table('campaign_vehicle_exposure_hourly')->insert($batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                DB::table('campaign_vehicle_exposure_hourly')->insert($batch);
            }
        });
    }
}
