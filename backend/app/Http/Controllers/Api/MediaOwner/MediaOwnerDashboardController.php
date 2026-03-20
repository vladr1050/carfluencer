<?php

namespace App\Http\Controllers\Api\MediaOwner;

use App\Http\Controllers\Controller;
use App\Models\CampaignVehicle;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaOwnerDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $vehicleIds = Vehicle::query()->where('media_owner_id', $user->id)->pluck('id');

        $activeCampaignLinks = CampaignVehicle::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereHas('campaign', fn ($q) => $q->where('status', 'active'))
            ->count();

        return response()->json([
            'vehicles_count' => $vehicleIds->count(),
            'active_campaign_participations' => $activeCampaignLinks,
        ]);
    }
}
