<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\AdPlacementPolicy;
use Illuminate\Http\JsonResponse;

class AdvertiserPricingController extends Controller
{
    public function index(): JsonResponse
    {
        $policies = AdPlacementPolicy::query()
            ->where('active', true)
            ->orderByRaw("CASE size_class WHEN 'S' THEN 1 WHEN 'M' THEN 2 WHEN 'L' THEN 3 WHEN 'XL' THEN 4 ELSE 5 END")
            ->get();

        return response()->json(['data' => $policies]);
    }
}
