<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\StopSession;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Per-vehicle and campaign-level rollups: daily_impressions + stop_sessions (driving/parking time).
 */
class CampaignVehicleTelemetryService
{
    /**
     * @return list<array{
     *     campaign_vehicle_id:int,
     *     vehicle_id:int,
     *     impressions:int,
     *     driving_distance_km:float,
     *     driving_time_hours:float,
     *     parking_time_hours:float,
     *     data_source:string
     * }>
     */
    public function rollupForCampaign(Campaign $campaign): array
    {
        $campaign->loadMissing('campaignVehicles.vehicle');

        $from = $campaign->start_date?->toDateString();
        $to = $campaign->end_date?->toDateString();

        $allImeis = $campaign->campaignVehicles
            ->map(fn (CampaignVehicle $cv) => $cv->vehicle?->imei)
            ->filter(fn ($i) => is_string($i) && $i !== '')
            ->unique()
            ->values()
            ->all();

        $drivingByImei = $allImeis === [] ? [] : $this->sumStopSessionMinutesGroupedByDevice($allImeis, 'driving', $from, $to);
        $parkingByImei = $allImeis === [] ? [] : $this->sumStopSessionMinutesGroupedByDevice($allImeis, 'parking', $from, $to);

        $out = [];
        foreach ($campaign->campaignVehicles as $cv) {
            $vehicle = $cv->vehicle;
            $imei = $vehicle?->imei;

            $q = DailyImpression::query()
                ->where('campaign_id', $campaign->id)
                ->where('vehicle_id', $cv->vehicle_id);
            $this->applyDateRange($q, 'stat_date', $from, $to);

            $impr = (int) $q->sum('impressions');
            $dist = (float) $q->sum('driving_distance_km');
            $parkMinDaily = (int) $q->sum('parking_minutes');

            $parkMinSessions = is_string($imei) && $imei !== ''
                ? (int) ($parkingByImei[$imei] ?? 0)
                : 0;
            $driveMinSessions = is_string($imei) && $imei !== ''
                ? (int) ($drivingByImei[$imei] ?? 0)
                : 0;

            $parkingHours = $parkMinDaily > 0
                ? round($parkMinDaily / 60, 1)
                : ($parkMinSessions > 0 ? round($parkMinSessions / 60, 1) : 0.0);

            $drivingHours = $driveMinSessions > 0
                ? round($driveMinSessions / 60, 1)
                : ($dist > 0 ? round($dist / 35, 1) : 0.0);

            $dataSource = match (true) {
                $impr > 0 || $dist > 0 || $parkMinDaily > 0 => 'daily_impressions',
                $driveMinSessions > 0 || $parkMinSessions > 0 => 'stop_sessions',
                default => 'none',
            };

            $out[] = [
                'campaign_vehicle_id' => (int) $cv->id,
                'vehicle_id' => (int) $cv->vehicle_id,
                'impressions' => $impr,
                'driving_distance_km' => round($dist, 2),
                'driving_time_hours' => $drivingHours,
                'parking_time_hours' => $parkingHours,
                'data_source' => $dataSource,
            ];
        }

        return $out;
    }

    public function summarizeForCampaign(Campaign $campaign): string
    {
        $rows = $this->rollupForCampaign($campaign);
        if ($rows === []) {
            return __('No vehicles linked.');
        }

        $impr = (int) array_sum(array_column($rows, 'impressions'));
        $km = (float) array_sum(array_column($rows, 'driving_distance_km'));
        $dh = (float) array_sum(array_column($rows, 'driving_time_hours'));
        $ph = (float) array_sum(array_column($rows, 'parking_time_hours'));

        $window = __('all dates');
        if ($campaign->start_date && $campaign->end_date) {
            $window = $campaign->start_date->toDateString().' → '.$campaign->end_date->toDateString();
        } elseif ($campaign->start_date) {
            $window = __('from :d', ['d' => $campaign->start_date->toDateString()]);
        } elseif ($campaign->end_date) {
            $window = __('until :d', ['d' => $campaign->end_date->toDateString()]);
        }

        return __('Impressions: :impr · Distance: :km km · Driving: :dh h · Parking: :ph h (:window).', [
            'impr' => number_format($impr),
            'km' => number_format($km, 1),
            'dh' => number_format($dh, 1),
            'ph' => number_format($ph, 1),
            'window' => $window,
        ]);
    }

    /**
     * Sum driving or parking session length (minutes) for vehicles on a campaign.
     *
     * @param  list<int>|null  $onlyVehicleIds  null = all vehicles on campaign; non-empty = subset; [] = none (0).
     */
    public function sumCampaignStopSessionMinutes(int $campaignId, string $kind, ?string $from, ?string $to, ?array $onlyVehicleIds = null): int
    {
        if ($onlyVehicleIds !== null && $onlyVehicleIds === []) {
            return 0;
        }

        $q = CampaignVehicle::query()->where('campaign_id', $campaignId);
        if ($onlyVehicleIds !== null) {
            $q->whereIn('vehicle_id', $onlyVehicleIds);
        }
        $vehicleIds = $q->pluck('vehicle_id');

        $imeis = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->pluck('imei')
            ->filter()
            ->values()
            ->all();

        return $this->sumStopSessionMinutesForImeis($imeis, $kind, $from, $to);
    }

    /**
     * @param  list<string>  $imeis
     */
    public function sumStopSessionMinutesForImeis(array $imeis, string $kind, ?string $from, ?string $to): int
    {
        if ($imeis === []) {
            return 0;
        }

        $driver = StopSession::query()->getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            return $this->sumStopSessionMinutesSqlTotal($imeis, $kind, $from, $to, $driver);
        }

        $total = 0;
        foreach ($imeis as $imei) {
            $total += $this->sumStopSessionMinutes($imei, $kind, $from, $to);
        }

        return $total;
    }

    /**
     * Minutes per device_id (for campaign rollups): one GROUP BY query on pgsql/mysql.
     *
     * @param  list<string>  $imeis
     * @return array<string, int>
     */
    public function sumStopSessionMinutesGroupedByDevice(array $imeis, string $kind, ?string $from, ?string $to): array
    {
        if ($imeis === []) {
            return [];
        }

        $driver = StopSession::query()->getConnection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'mysql'], true)) {
            $out = [];
            foreach ($imeis as $imei) {
                $out[$imei] = $this->sumStopSessionMinutes($imei, $kind, $from, $to);
            }

            return $out;
        }

        $q = StopSession::query()
            ->whereIn('device_id', $imeis)
            ->where('kind', $kind)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');
        $this->applyStopSessionStartedAtRange($q, $from, $to);

        $expr = $driver === 'pgsql'
            ? 'COALESCE(ROUND(SUM(GREATEST(0, EXTRACT(EPOCH FROM (ended_at - started_at))) / 60.0))::bigint, 0)'
            : 'COALESCE(ROUND(SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, ended_at)) / 60.0)), 0)';

        $rows = $q->clone()
            ->selectRaw('device_id, '.$expr.' as mins')
            ->groupBy('device_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->device_id] = (int) $row->mins;
        }

        return $map;
    }

    /**
     * @param  list<string>  $imeis
     */
    private function sumStopSessionMinutesSqlTotal(array $imeis, string $kind, ?string $from, ?string $to, string $driver): int
    {
        $q = StopSession::query()
            ->whereIn('device_id', $imeis)
            ->where('kind', $kind)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');
        $this->applyStopSessionStartedAtRange($q, $from, $to);

        $expr = $driver === 'pgsql'
            ? 'COALESCE(ROUND(SUM(GREATEST(0, EXTRACT(EPOCH FROM (ended_at - started_at))) / 60.0))::bigint, 0)'
            : 'COALESCE(ROUND(SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, ended_at)) / 60.0)), 0)';

        return (int) $q->clone()->selectRaw($expr.' as total')->value('total');
    }

    private function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate($column, '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function sumStopSessionMinutes(string $imei, string $kind, ?string $from, ?string $to): int
    {
        $q = StopSession::query()
            ->where('device_id', $imei)
            ->where('kind', $kind)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at');
        $this->applyStopSessionStartedAtRange($q, $from, $to);

        $total = 0;
        foreach ($q->cursor() as $session) {
            $total += (int) round($session->started_at->diffInSeconds($session->ended_at) / 60);
        }

        return $total;
    }

    private function applyStopSessionStartedAtRange(Builder $query, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->where('started_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to !== null && $to !== '') {
            $query->where('started_at', '<', Carbon::parse($to)->addDay()->startOfDay());
        }
    }
}
