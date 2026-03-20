<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::query()
            ->where('advertiser_id', $request->user()->id)
            ->with('campaignVehicles.vehicle')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);
        $campaign->load(['campaignVehicles.vehicle', 'advertiser']);

        return response()->json($campaign);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $platformPct = PlatformSetting::get('platform_commission_percent', '10');
        $agencyPct = PlatformSetting::get('default_agency_commission_percent', '0');

        $campaign = Campaign::query()->create([
            'advertiser_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_by_admin' => false,
            'created_by_user_id' => $request->user()->id,
            'platform_commission_percent' => $platformPct,
            'agency_commission_percent' => $agencyPct,
            'discount_percent' => $request->user()->advertiserProfile?->discount_percent,
        ]);

        return response()->json($campaign, 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'string', 'max:32'],
        ]);

        $campaign->update($data);

        return response()->json($campaign);
    }

}
