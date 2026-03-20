<?php

namespace App\Http\Controllers\Api\MediaOwner;

use App\Http\Controllers\Controller;
use App\Models\CampaignVehicle;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaOwnerEarningsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicleIds = Vehicle::query()->where('media_owner_id', $request->user()->id)->pluck('id');

        $byVehicle = CampaignVehicle::query()
            ->select([
                'vehicle_id',
                DB::raw('SUM(CAST(agreed_price AS DECIMAL(14,2))) as total'),
            ])
            ->whereIn('vehicle_id', $vehicleIds)
            ->groupBy('vehicle_id')
            ->get();

        $total = $byVehicle->sum('total');

        return response()->json([
            'total_agreed_price' => (string) $total,
            'by_vehicle' => $byVehicle,
        ]);
    }
}
