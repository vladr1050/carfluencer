<?php

namespace App\Services\Telemetry;

use App\Models\GeoZone;
use App\Models\StopSession;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ZoneAttributionService
{
    /**
     * @param  list<string>|null  $onlyDeviceIds  When set, only parking sessions for these {@see StopSession::device_id} values (e.g. campaign IMEIs).
     */
    public function attributeParkingSessionsForDate(CarbonInterface $date, ?array $onlyDeviceIds = null): void
    {
        $zones = GeoZone::query()->where('active', true)->get();
        if ($zones->isEmpty()) {
            return;
        }

        $rangeStart = Carbon::parse($date->toDateString(), 'UTC');
        $rangeEndExclusive = $rangeStart->copy()->addDay();

        $q = StopSession::query()
            ->where('kind', 'parking')
            ->where('started_at', '>=', $rangeStart)
            ->where('started_at', '<', $rangeEndExclusive);
        if ($onlyDeviceIds !== null && $onlyDeviceIds !== []) {
            $q->whereIn('device_id', array_values(array_unique($onlyDeviceIds)));
        }

        $sessions = $q->get();

        foreach ($sessions as $session) {
            $session->zones()->detach();
            foreach ($zones as $zone) {
                if ($zone->containsPoint((float) $session->center_latitude, (float) $session->center_longitude)) {
                    $session->zones()->syncWithoutDetaching([$zone->id]);
                }
            }
        }
    }
}
