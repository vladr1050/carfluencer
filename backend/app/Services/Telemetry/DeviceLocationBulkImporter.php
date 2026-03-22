<?php

namespace App\Services\Telemetry;

use App\Models\DeviceLocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DeviceLocationBulkImporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return int Number of rows attempted (including duplicates skipped by upsert)
     */
    public function import(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $batch = [];
        $count = 0;
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);
            if ($mapped === null) {
                continue;
            }
            $batch[] = $mapped;
            $count++;
            if (count($batch) >= 250) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->upsertBatch($batch);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $deviceId = $row['device_id'] ?? null;
        if (! is_string($deviceId) && ! is_numeric($deviceId)) {
            return null;
        }
        $deviceId = (string) $deviceId;

        $eventAt = $this->parseTime($row['event_at'] ?? $row['timestamp'] ?? null);
        if ($eventAt === null) {
            return null;
        }

        $lat = $row['latitude'] ?? null;
        $lng = $row['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $ignition = $row['ignition'] ?? null;
        if ($ignition !== null) {
            $ignition = filter_var($ignition, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $extra = $row['extra_json'] ?? null;
        if ($extra !== null && ! is_array($extra)) {
            $extra = null;
        }
        if ($extra === null && isset($row['io']) && is_string($row['io'])) {
            $decoded = json_decode($row['io'], true);
            $extra = is_array($decoded) ? $decoded : null;
        }

        return [
            'device_id' => $deviceId,
            'event_at' => $eventAt->format('Y-m-d H:i:s.u'),
            'latitude' => round((float) $lat, 7),
            'longitude' => round((float) $lng, 7),
            'speed' => isset($row['speed']) && is_numeric($row['speed']) ? round((float) $row['speed'], 2) : null,
            'battery' => isset($row['battery']) && is_numeric($row['battery']) ? (int) $row['battery'] : null,
            'gsm_signal' => isset($row['gsm_signal']) && is_numeric($row['gsm_signal']) ? (int) $row['gsm_signal'] : null,
            'odometer' => isset($row['odometer']) && is_numeric($row['odometer']) ? round((float) $row['odometer'], 3) : null,
            'ignition' => $ignition,
            'extra_json' => $extra !== null ? json_encode($extra) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function parseTime(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC((int) $value);
        }
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value, 'UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function upsertBatch(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        // PostgreSQL: ON CONFLICT DO UPDATE cannot touch the same row twice in one INSERT.
        // ClickHouse can return duplicate (device_id, event_at) in one pull; collapse to last row.
        $batch = $this->deduplicateBatchByDeviceAndEventAt($batch);

        DB::transaction(function () use ($batch): void {
            DeviceLocation::query()->upsert(
                $batch,
                ['device_id', 'event_at'],
                ['latitude', 'longitude', 'speed', 'battery', 'gsm_signal', 'odometer', 'ignition', 'extra_json', 'updated_at']
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @return list<array<string, mixed>>
     */
    private function deduplicateBatchByDeviceAndEventAt(array $batch): array
    {
        $deduped = [];
        foreach ($batch as $row) {
            $deviceId = (string) ($row['device_id'] ?? '');
            $eventAt = (string) ($row['event_at'] ?? '');
            $deduped[$deviceId."\0".$eventAt] = $row;
        }

        return array_values($deduped);
    }
}
