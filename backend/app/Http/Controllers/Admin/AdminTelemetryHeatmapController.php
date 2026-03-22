<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\Telemetry\AdminHeatmapDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTelemetryHeatmapController extends Controller
{
    /**
     * JSON for admin heatmap: campaign | one vehicle | group of vehicles, period, motion filter.
     */
    public function data(Request $request, AdminHeatmapDataService $heatmap): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'scope' => ['required', 'string', Rule::in(['campaign', 'vehicle', 'vehicles'])],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id', 'required_if:scope,campaign'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id', 'required_if:scope,vehicle'],
            'vehicle_ids' => ['nullable', 'array', 'required_if:scope,vehicles'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'motion' => ['nullable', 'string', Rule::in(['moving', 'stopped', 'both'])],
        ]);

        if ($data['scope'] === 'vehicles') {
            $ids = array_values(array_filter(array_map('intval', $data['vehicle_ids'] ?? [])));
            if ($ids === []) {
                return response()->json([
                    'error' => 'Select at least one vehicle for group scope.',
                ], 422);
            }
            $data['vehicle_ids'] = $ids;
        }

        $motion = $data['motion'] ?? 'both';

        $result = $heatmap->build([
            'scope' => $data['scope'],
            'campaign_id' => $data['campaign_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'vehicle_ids' => $data['vehicle_ids'] ?? [],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'motion' => $motion,
        ]);

        $vehiclesPayload = $this->resolveVehiclesPayload($data);

        return response()
            ->json([
                'filter' => [
                    'scope' => $data['scope'],
                    'motion' => $motion,
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null,
                ],
                'vehicles' => $vehiclesPayload,
                'heatmap' => [
                    'points' => $result['points'],
                    'metrics' => $result['meta'],
                ],
            ])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function resolveVehiclesPayload(array $data): array
    {
        if ($data['scope'] === 'vehicle') {
            $v = Vehicle::query()->find($data['vehicle_id'] ?? null);
            if ($v === null) {
                return [];
            }

            return [[
                'id' => $v->id,
                'imei' => $v->imei,
                'brand' => $v->brand,
                'model' => $v->model,
            ]];
        }

        if ($data['scope'] === 'vehicles') {
            return Vehicle::query()
                ->whereIn('id', $data['vehicle_ids'] ?? [])
                ->get()
                ->map(fn (Vehicle $v) => [
                    'id' => $v->id,
                    'imei' => $v->imei,
                    'brand' => $v->brand,
                    'model' => $v->model,
                ])
                ->values()
                ->all();
        }

        if ($data['scope'] === 'campaign' && ! empty($data['campaign_id'])) {
            return Vehicle::query()
                ->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $data['campaign_id']))
                ->orderBy('id')
                ->get()
                ->map(fn (Vehicle $v) => [
                    'id' => $v->id,
                    'imei' => $v->imei,
                    'brand' => $v->brand,
                    'model' => $v->model,
                ])
                ->values()
                ->all();
        }

        return [];
    }
}
