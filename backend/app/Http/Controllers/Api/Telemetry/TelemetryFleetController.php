<?php

namespace App\Http\Controllers\Api\Telemetry;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telemetry-oriented fleet/campaign listings (device_id = vehicles.imei).
 */
class TelemetryFleetController extends Controller
{
    public function vehicles(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isMediaOwner()) {
            $rows = Vehicle::query()
                ->where('media_owner_id', $user->id)
                ->orderBy('id')
                ->get(['id', 'imei', 'brand', 'model', 'status']);

            return response()->json(['data' => $rows]);
        }

        abort_unless($user->isAdvertiser(), 403);

        $ids = Vehicle::query()
            ->whereHas('campaigns', fn ($q) => $q->where('campaigns.advertiser_id', $user->id))
            ->pluck('id');

        $rows = Vehicle::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'imei', 'brand', 'model', 'status']);

        return response()->json(['data' => $rows]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdvertiser()) {
            $rows = Campaign::query()
                ->where('advertiser_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'status', 'start_date', 'end_date']);

            return response()->json(['data' => $rows]);
        }

        abort_unless($user->isMediaOwner(), 403);

        $rows = Campaign::query()
            ->whereHas('vehicles', fn ($q) => $q->where('vehicles.media_owner_id', $user->id))
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'start_date', 'end_date']);

        return response()->json(['data' => $rows]);
    }
}
