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
            ->where('status', 'active')
            ->with('mediaOwner:id,name,company_name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($vehicles);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);
        $vehicle->load('mediaOwner:id,name,company_name');

        return response()->json($vehicle);
    }
}
