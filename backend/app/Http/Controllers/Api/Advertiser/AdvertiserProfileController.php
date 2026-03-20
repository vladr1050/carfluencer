<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserProfileController extends Controller
{
    public function discounts(Request $request): JsonResponse
    {
        $profile = $request->user()->advertiserProfile;

        return response()->json([
            'profile_discount_percent' => $profile?->discount_percent,
            'agency_commission_percent' => $profile?->agency_commission_percent,
        ]);
    }
}
