<?php

namespace App\Services\ImpressionEngine;

use App\Models\MobilityReferenceCell;
use App\Services\ImpressionEngine\Geo\HaversineMeters;

/**
 * Buckets mobility cells by coarse lat/lng for nearest-neighbour within maxMeters.
 */
final class MobilitySpatialIndex
{
    private const BUCKET_DEG = 0.05;

    /** @var array<string, list<array{cell_id: string, lat: float, lng: float, row: array<string, mixed>}>> */
    private array $buckets = [];

    /**
     * @param  iterable<int, MobilityReferenceCell>  $cells
     */
    public function __construct(iterable $cells)
    {
        foreach ($cells as $cell) {
            $lat = (float) $cell->lat_center;
            $lng = (float) $cell->lng_center;
            if ($lat === 0.0 && $lng === 0.0) {
                continue;
            }
            $row = [
                'vehicle_aadt' => (int) $cell->vehicle_aadt,
                'pedestrian_daily' => (int) $cell->pedestrian_daily,
                'average_speed_kmh' => (float) $cell->average_speed_kmh,
                'hourly_peak_factor' => (float) $cell->hourly_peak_factor,
            ];
            $entry = [
                'cell_id' => $cell->cell_id,
                'lat' => $lat,
                'lng' => $lng,
                'row' => $row,
            ];
            $bk = $this->bucketKey($lat, $lng);
            $this->buckets[$bk][] = $entry;
        }
    }

    /**
     * @return array{row: array<string, mixed>, mobility_cell_id: string}|null
     */
    public function nearestWithin(float $lat, float $lng, float $maxMeters): ?array
    {
        $bi = (int) floor($lat / self::BUCKET_DEG);
        $bj = (int) floor($lng / self::BUCKET_DEG);
        $bestD = INF;
        $best = null;
        for ($di = -1; $di <= 1; $di++) {
            for ($dj = -1; $dj <= 1; $dj++) {
                $key = ($bi + $di).'_'.($bj + $dj);
                foreach ($this->buckets[$key] ?? [] as $e) {
                    $d = HaversineMeters::distance($lat, $lng, $e['lat'], $e['lng']);
                    if ($d < $bestD && $d <= $maxMeters) {
                        $bestD = $d;
                        $best = ['row' => $e['row'], 'mobility_cell_id' => $e['cell_id']];
                    }
                }
            }
        }

        return $best;
    }

    private function bucketKey(float $lat, float $lng): string
    {
        return (int) floor($lat / self::BUCKET_DEG).'_'.(int) floor($lng / self::BUCKET_DEG);
    }
}
