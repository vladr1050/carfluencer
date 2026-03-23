<?php

namespace App\Services\Telemetry;

use App\Models\DeviceLocation;
use App\Models\StopSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StopSessionBuilderService
{
    public function __construct(
        private readonly ZoneAttributionService $zoneAttribution,
    ) {}

    /**
     * Rebuild stop/driving sessions for all devices that have pings on this calendar day (UTC date).
     */
    public function buildForDate(CarbonInterface $date): int
    {
        $dateStr = $date->toDateString();

        $deviceIds = DeviceLocation::query()
            ->whereDate('event_at', $dateStr)
            ->distinct()
            ->pluck('device_id');

        $total = 0;
        foreach ($deviceIds as $deviceId) {
            StopSession::query()
                ->where('device_id', $deviceId)
                ->whereDate('started_at', $dateStr)
                ->delete();

            $points = DeviceLocation::query()
                ->where('device_id', $deviceId)
                ->whereDate('event_at', $dateStr)
                ->orderBy('event_at')
                ->get();

            $total += $this->buildSessionsForPoints((string) $deviceId, $points);
        }

        $this->zoneAttribution->attributeParkingSessionsForDate($date);

        return $total;
    }

    /**
     * @param  Collection<int, DeviceLocation>  $points
     */
    private function buildSessionsForPoints(string $deviceId, Collection $points): int
    {
        if ($points->isEmpty()) {
            return 0;
        }

        $minSec = (int) config('telemetry.min_session_seconds');

        $sessions = [];
        $currentKind = null;
        /** @var list<DeviceLocation> $bucket */
        $bucket = [];

        foreach ($points as $p) {
            $kind = DeviceLocationMotionScope::isParkingState($p->ignition, $p->speed) ? 'parking' : 'driving';
            if ($currentKind === null) {
                $currentKind = $kind;
                $bucket = [$p];

                continue;
            }
            if ($kind !== $currentKind) {
                $row = $this->flushBucket($deviceId, $bucket, $currentKind, $minSec);
                if ($row !== null) {
                    $sessions[] = $row;
                }
                $currentKind = $kind;
                $bucket = [$p];
            } else {
                $bucket[] = $p;
            }
        }

        $row = $this->flushBucket($deviceId, $bucket, $currentKind, $minSec);
        if ($row !== null) {
            $sessions[] = $row;
        }

        foreach ($sessions as $row) {
            StopSession::query()->create($row);
        }

        return count($sessions);
    }

    /**
     * @param  list<DeviceLocation>  $bucket
     * @return array<string, mixed>|null
     */
    private function flushBucket(string $deviceId, array $bucket, ?string $currentKind, int $minSec): ?array
    {
        if ($bucket === [] || $currentKind === null) {
            return null;
        }

        $start = $bucket[0]->event_at;
        $end = $bucket[count($bucket) - 1]->event_at;
        if ($start === null || $end === null || $start->diffInSeconds($end) < $minSec) {
            return null;
        }

        $avgLat = collect($bucket)->avg(fn (DeviceLocation $p) => (float) $p->latitude);
        $avgLng = collect($bucket)->avg(fn (DeviceLocation $p) => (float) $p->longitude);

        return [
            'device_id' => $deviceId,
            'started_at' => $start,
            'ended_at' => $end,
            'center_latitude' => round((float) $avgLat, 7),
            'center_longitude' => round((float) $avgLng, 7),
            'point_count' => count($bucket),
            'kind' => $currentKind,
        ];
    }
}
