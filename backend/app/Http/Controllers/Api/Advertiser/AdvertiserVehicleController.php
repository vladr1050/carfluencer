<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 30), 1), 200);

        $vehicles = Vehicle::query()
            ->whereIn('status', Vehicle::catalogVisibleStatuses())
            ->with('mediaOwner:id,name,company_name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($vehicles);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $vehicle->load([
            'mediaOwner:id,name,company_name',
            'campaigns' => fn ($q) => $q
                ->select('campaigns.id', 'campaigns.name', 'campaigns.status', 'campaigns.start_date', 'campaigns.end_date', 'campaigns.advertiser_id')
                ->where('campaigns.advertiser_id', $request->user()->id),
        ]);

        return response()->json($vehicle);
    }
}
