<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Analytics\CampaignParkingByZoneService;
use App\Services\Telemetry\HeatmapRequestDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserCampaignParkingByZoneController extends Controller
{
    public function __construct(
        private readonly CampaignParkingByZoneService $parkingByZoneService
    ) {}

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
        ]);

        HeatmapRequestDateRange::assertWithinConfiguredLimit($data['date_from'] ?? null, $data['date_to'] ?? null);

        $this->authorize('viewAnalytics', $campaign);

        $vehicleFilter = isset($data['vehicle_id']) ? [(int) $data['vehicle_id']] : [];

        $payload = $this->parkingByZoneService->build(
            (int) $campaign->id,
            (string) $data['date_from'],
            (string) $data['date_to'],
            $vehicleFilter
        );

        return response()->json([
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
            ],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'parking_by_zone' => $payload,
        ])->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    }
}
