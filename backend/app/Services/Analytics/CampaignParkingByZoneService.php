<?php

namespace App\Services\Analytics;

use App\Models\GeoZone;
use App\Models\StopSession;
use App\Models\Vehicle;
use App\Services\Telemetry\HeatmapPageQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Parking time (minutes) by {@see GeoZone} for campaign vehicles, from {@see StopSession} kind=parking.
 *
 * Attribution: session center (center_latitude/center_longitude) inside zone polygon if set, else bbox; uses pivot zones when
 * present (active zones only), otherwise evaluates all active GeoZones. Full clipped minutes are credited
 * to each matching zone — sums across zones can exceed unique session time when zones overlap.
 */
final class CampaignParkingByZoneService
{
    /**
     * @param  list<int>  $vehicleIdsFilter  Empty = all vehicles on campaign (same as heatmap snapshot).
     * @return array{
     *     definition: string,
     *     overlap_note: string,
     *     totals: array{parking_minutes_in_window: int, parking_sessions_in_window: int, vehicles: int},
     *     by_zone: list<array{zone_id: int, code: string, name: string, parking_minutes: int, sessions_count: int, vehicles_distinct: int}>,
     *     unattributed: array{parking_minutes: int, sessions_count: int},
     *     by_vehicle: list<array{vehicle_id: int, parking_minutes_total: int, by_zone: list<array{zone_id: int, code: string, name: string, parking_minutes: int}>, unattributed_parking_minutes: int}>
     * }
     */
    public function build(int $campaignId, string $dateFrom, string $dateTo, array $vehicleIdsFilter = []): array
    {
        $pageQuery = new HeatmapPageQuery(
            campaignId: $campaignId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            vehicleIdsFilter: array_values(array_map('intval', $vehicleIdsFilter)),
            mode: 'parking',
            normalization: 'max',
            south: null,
            west: null,
            north: null,
            east: null,
            zoom: null,
        );

        /** @var list<int> $vehicleIds */
        $vehicleIds = $pageQuery->resolveCampaignVehicleIds()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($vehicleIds === []) {
            return $this->emptyPayload();
        }

        /** @var Collection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get()
            ->keyBy('id');

        $imeis = $vehicles->pluck('imei')->filter(fn ($i) => is_string($i) && $i !== '')->values()->all();
        if ($imeis === []) {
            return $this->emptyPayload(count($vehicleIds));
        }

        $windowStart = CarbonImmutable::parse($dateFrom, 'UTC')->startOfDay();
        $windowEnd = CarbonImmutable::parse($dateTo, 'UTC')->endOfDay();

        /** @var Collection<int, GeoZone> $activeZones */
        $activeZones = GeoZone::query()->where('active', true)->orderBy('id')->get()->keyBy('id');

        $sessions = StopSession::query()
            ->where('kind', 'parking')
            ->whereIn('device_id', $imeis)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->where('started_at', '<', $windowEnd)
            ->where('ended_at', '>', $windowStart)
            ->with('zones')
            ->get();

        $zoneAgg = [];
        $unattribMin = 0;
        $unattribSessions = 0;
        $totalUniqueMin = 0;
        $sessionCount = 0;

        $perVehicle = [];
        foreach ($vehicleIds as $vid) {
            $perVehicle[$vid] = [
                'zones' => [],
                'unattrib' => 0,
                'total' => 0,
            ];
        }

        foreach ($sessions as $session) {
            $clipStart = $session->started_at->max($windowStart);
            $clipEnd = $session->ended_at->min($windowEnd);
            if ($clipEnd->lessThanOrEqualTo($clipStart)) {
                continue;
            }

            $mins = (int) max(0, round($clipStart->diffInSeconds($clipEnd) / 60.0));
            if ($mins <= 0) {
                continue;
            }

            $sessionCount++;
            $totalUniqueMin += $mins;

            $vehicleId = $this->vehicleIdForImei($vehicles, (string) $session->device_id);
            if ($vehicleId !== null) {
                $perVehicle[$vehicleId]['total'] += $mins;
            }

            $zoneIds = $this->resolveZoneIdsForSession($session, $activeZones);
            if ($zoneIds === []) {
                $unattribMin += $mins;
                $unattribSessions++;
                if ($vehicleId !== null) {
                    $perVehicle[$vehicleId]['unattrib'] += $mins;
                }

                continue;
            }

            foreach ($zoneIds as $zid) {
                if (! isset($zoneAgg[$zid])) {
                    $zoneAgg[$zid] = ['minutes' => 0, 'sessions' => 0, 'imeis' => []];
                }
                $zoneAgg[$zid]['minutes'] += $mins;
                $zoneAgg[$zid]['sessions']++;
                $zoneAgg[$zid]['imeis'][(string) $session->device_id] = true;

                if ($vehicleId !== null) {
                    $perVehicle[$vehicleId]['zones'][$zid] = ($perVehicle[$vehicleId]['zones'][$zid] ?? 0) + $mins;
                }
            }
        }

        $byZone = [];
        foreach ($zoneAgg as $zid => $row) {
            $z = $activeZones->get((int) $zid);
            if ($z === null) {
                continue;
            }
            $byZone[] = [
                'zone_id' => (int) $zid,
                'code' => (string) $z->code,
                'name' => (string) $z->name,
                'parking_minutes' => (int) $row['minutes'],
                'sessions_count' => (int) $row['sessions'],
                'vehicles_distinct' => count($row['imeis']),
            ];
        }

        usort($byZone, static fn (array $a, array $b): int => ($b['parking_minutes'] ?? 0) <=> ($a['parking_minutes'] ?? 0));

        $byVehicleOut = [];
        foreach ($vehicleIds as $vid) {
            $row = $perVehicle[$vid] ?? ['zones' => [], 'unattrib' => 0, 'total' => 0];
            $bz = [];
            foreach ($row['zones'] as $zid => $m) {
                $z = $activeZones->get((int) $zid);
                if ($z === null) {
                    continue;
                }
                $bz[] = [
                    'zone_id' => (int) $zid,
                    'code' => (string) $z->code,
                    'name' => (string) $z->name,
                    'parking_minutes' => (int) $m,
                ];
            }
            usort($bz, static fn (array $a, array $b): int => ($b['parking_minutes'] ?? 0) <=> ($a['parking_minutes'] ?? 0));
            $byVehicleOut[] = [
                'vehicle_id' => $vid,
                'parking_minutes_total' => (int) $row['total'],
                'by_zone' => $bz,
                'unattributed_parking_minutes' => (int) $row['unattrib'],
            ];
        }

        return [
            'definition' => 'Parking minutes = overlap of each stop_session (kind=parking) with [date_from 00:00 UTC, date_to 23:59:59 UTC], rounded to whole minutes. A session is attributed to every active GeoZone whose polygon (or bbox if no polygon) contains its center; pivot stop_session_zone is used when present (active zones only).',
            'overlap_note' => 'Per-zone parking time may sum to more than the total unique window time when zones overlap or one session is credited to multiple zones.',
            'totals' => [
                'parking_minutes_in_window' => $totalUniqueMin,
                'parking_sessions_in_window' => $sessionCount,
                'vehicles' => count($vehicleIds),
            ],
            'by_zone' => $byZone,
            'unattributed' => [
                'parking_minutes' => $unattribMin,
                'sessions_count' => $unattribSessions,
            ],
            'by_vehicle' => $byVehicleOut,
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function vehicleIdForImei(Collection $vehicles, string $imei): ?int
    {
        foreach ($vehicles as $v) {
            if ((string) $v->imei === $imei) {
                return (int) $v->id;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, GeoZone>  $activeZones
     * @return list<int>
     */
    private function resolveZoneIdsForSession(StopSession $session, Collection $activeZones): array
    {
        $fromPivot = $session->zones
            ->filter(fn (GeoZone $z) => $activeZones->has($z->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($fromPivot !== []) {
            return $fromPivot;
        }

        $lat = (float) $session->center_latitude;
        $lng = (float) $session->center_longitude;
        $ids = [];
        foreach ($activeZones as $z) {
            if ($z->containsPoint($lat, $lng)) {
                $ids[] = (int) $z->id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(int $vehicles = 0): array
    {
        return [
            'definition' => 'Parking minutes = overlap of each stop_session (kind=parking) with the selected UTC date window.',
            'overlap_note' => 'Per-zone parking time may sum to more than the total unique window time when zones overlap.',
            'totals' => [
                'parking_minutes_in_window' => 0,
                'parking_sessions_in_window' => 0,
                'vehicles' => $vehicles,
            ],
            'by_zone' => [],
            'unattributed' => [
                'parking_minutes' => 0,
                'sessions_count' => 0,
            ],
            'by_vehicle' => [],
        ];
    }
}
