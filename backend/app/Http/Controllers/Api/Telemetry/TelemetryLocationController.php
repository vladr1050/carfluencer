<?php

namespace App\Http\Controllers\Api\Telemetry;

use App\Http\Controllers\Controller;
use App\Models\DeviceLocation;
use App\Services\Telemetry\TelemetryGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryLocationController extends Controller
{
    /**
     * Raw device pings stored in PostgreSQL (after ClickHouse collector sync).
     */
    public function raw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'imei' => ['required', 'string', 'max:64'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        abort_unless(TelemetryGate::canAccessImei($request->user(), $data['imei']), 403);

        $rows = DeviceLocation::query()
            ->where('device_id', $data['imei'])
            ->whereDate('event_at', '>=', $data['date_from'])
            ->whereDate('event_at', '<=', $data['date_to'])
            ->orderBy('event_at')
            ->limit(50_000)
            ->get()
            ->map(fn (DeviceLocation $r) => [
                'device_id' => $r->device_id,
                'timestamp' => $r->event_at?->toIso8601String(),
                'latitude' => (float) $r->latitude,
                'longitude' => (float) $r->longitude,
                'speed' => $r->speed !== null ? (float) $r->speed : null,
                'battery' => $r->battery,
                'gsm_signal' => $r->gsm_signal,
                'odometer' => $r->odometer !== null ? (float) $r->odometer : null,
                'ignition' => $r->ignition,
                'extra_json' => $r->extra_json,
            ]);

        return response()->json(['data' => $rows]);
    }
}
