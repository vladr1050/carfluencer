<?php

namespace App\Services\Telemetry;

use App\Models\GeoZone;
use App\Models\StopSession;
use Carbon\CarbonInterface;

class ZoneAttributionService
{
    public function attributeParkingSessionsForDate(CarbonInterface $date): void
    {
        $zones = GeoZone::query()->where('active', true)->get();
        if ($zones->isEmpty()) {
            return;
        }

        $sessions = StopSession::query()
            ->whereDate('started_at', $date->toDateString())
            ->where('kind', 'parking')
            ->get();

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
