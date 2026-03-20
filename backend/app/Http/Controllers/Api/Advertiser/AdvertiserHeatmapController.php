<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Telemetry\HeatmapDataServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'mode' => ['nullable', 'string', 'in:driving,parking,both'],
        ]);

        $campaign = Campaign::query()->findOrFail($data['campaign_id']);
        $this->authorize('viewAnalytics', $campaign);

        $filters = [
            'vehicle_ids' => isset($data['vehicle_id']) ? [(int) $data['vehicle_id']] : [],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'mode' => $data['mode'] ?? 'both',
        ];

        $payload = $this->heatmapData->fetchHeatmapData((int) $campaign->id, $filters);

        return response()->json([
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'start_date' => $campaign->start_date?->format('Y-m-d'),
                'end_date' => $campaign->end_date?->format('Y-m-d'),
            ],
            'heatmap' => $payload,
        ]);
    }
}
