<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\Vehicle;

/**
 * Resolves device IMEIs for bulk ClickHouse → PostgreSQL telemetry sync scopes.
 */
class TelemetrySyncImeiResolver
{
    /**
     * @param  list<int>  $vehicleIds
     * @param  bool  $onlyTelemetryPullEnabled  When true and scope is `all_vehicles`, only vehicles with scheduled pull enabled (used by the platform scheduler).
     * @return list<string>
     */
    public function resolve(string $scope, ?int $campaignId = null, array $vehicleIds = [], bool $onlyTelemetryPullEnabled = false): array
    {
        return match ($scope) {
            'all_vehicles' => $this->allVehicleImeis($onlyTelemetryPullEnabled),
            'campaign' => $this->campaignImeis($campaignId),
            'vehicles' => $this->vehicleIdsToImeis($vehicleIds),
            default => [],
        };
    }

    /**
     * IMEI для фонового incremental: только включённые на тягу, сначала те, кого давно не трогали
     * (честное распределение нагрузки на ClickHouse между объектами).
     *
     * @return list<string>
     */
    public function orderedImeisForScheduledIncrementalPull(): array
    {
        return Vehicle::query()
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->where('telemetry_pull_enabled', true)
            ->orderByRaw('COALESCE(telemetry_last_incremental_at, ?) ASC', ['1970-01-01 00:00:00'])
            ->orderBy('id')
            ->pluck('imei')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allVehicleImeis(bool $onlyTelemetryPullEnabled): array
    {
        $q = Vehicle::query()
            ->whereNotNull('imei')
            ->where('imei', '!=', '');

        if ($onlyTelemetryPullEnabled) {
            $q->where('telemetry_pull_enabled', true);
        }

        return $q->pluck('imei')->all();
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return list<string>
     */
    private function vehicleIdsToImeis(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        return Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->pluck('imei')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function campaignImeis(?int $campaignId): array
    {
        if ($campaignId === null) {
            return [];
        }

        $campaign = Campaign::query()->find($campaignId);
        if ($campaign === null) {
            return [];
        }

        return $campaign->vehicles()
            ->whereNotNull('imei')
            ->where('imei', '!=', '')
            ->pluck('imei')
            ->all();
    }
}
