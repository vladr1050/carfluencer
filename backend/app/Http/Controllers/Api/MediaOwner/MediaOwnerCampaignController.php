<?php

namespace App\Http\Controllers\Api\MediaOwner;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaOwnerCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = Vehicle::query()->where('media_owner_id', $request->user()->id)->pluck('id');

        $campaigns = Campaign::query()
            ->whereHas('vehicles', fn ($q) => $q->whereIn('vehicles.id', $vehicleIds))
            ->with(['advertiser:id,name,email,company_name', 'vehicles' => fn ($q) => $q->whereIn('vehicles.id', $vehicleIds)])
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $vehicleIds = Vehicle::query()->where('media_owner_id', $request->user()->id)->pluck('id');

        $campaign->load([
            'advertiser:id,name,email,company_name',
            'vehicles' => fn ($q) => $q->whereIn('vehicles.id', $vehicleIds),
        ]);

        return response()->json($campaign);
    }
}
