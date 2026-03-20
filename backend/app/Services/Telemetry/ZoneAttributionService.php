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
                if ($this->pointInBBox((float) $session->center_latitude, (float) $session->center_longitude, $zone)) {
                    $session->zones()->syncWithoutDetaching([$zone->id]);
                }
            }
        }
    }

    private function pointInBBox(float $lat, float $lng, GeoZone $zone): bool
    {
        return $lat >= $zone->min_lat && $lat <= $zone->max_lat
            && $lng >= $zone->min_lng && $lng <= $zone->max_lng;
    }
}
