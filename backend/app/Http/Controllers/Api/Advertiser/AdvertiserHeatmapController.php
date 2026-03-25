<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Telemetry\HeatmapDataServiceInterface;
use App\Services\Telemetry\HeatmapRequestDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvertiserHeatmapController extends Controller
{
    public function __construct(
        private readonly HeatmapDataServiceInterface $heatmapData
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'mode' => ['nullable', 'string', 'in:driving,parking'],
            'normalization' => ['nullable', 'string', Rule::in(['max', 'p95', 'p99'])],
            'south' => ['nullable', 'numeric', 'between:-90,90'],
            'west' => ['nullable', 'numeric', 'between:-180,180'],
            'north' => ['nullable', 'numeric', 'between:-90,90'],
            'east' => ['nullable', 'numeric', 'between:-180,180'],
            'zoom' => ['nullable', 'integer', 'min:1', 'max:22'],
        ]);

        HeatmapRequestDateRange::assertWithinConfiguredLimit($data['date_from'] ?? null, $data['date_to'] ?? null);

        $campaign = Campaign::query()->findOrFail($data['campaign_id']);
        $this->authorize('viewAnalytics', $campaign);

        $mode = $data['mode'] ?? 'driving';
        if ($mode === 'both') {
            $mode = 'driving';
        }

        $filters = [
            'vehicle_ids' => isset($data['vehicle_id']) ? [(int) $data['vehicle_id']] : [],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'mode' => $mode,
            'normalization' => $data['normalization'] ?? 'p95',
            'south' => $data['south'] ?? null,
            'west' => $data['west'] ?? null,
            'north' => $data['north'] ?? null,
            'east' => $data['east'] ?? null,
            'zoom' => $data['zoom'] ?? null,
        ];

        $payload = $this->heatmapData->fetchHeatmapData((int) $campaign->id, $filters);

        return response()
            ->json([
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'start_date' => $campaign->start_date?->format('Y-m-d'),
                    'end_date' => $campaign->end_date?->format('Y-m-d'),
                ],
                'heatmap' => $payload,
            ])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    }
}
