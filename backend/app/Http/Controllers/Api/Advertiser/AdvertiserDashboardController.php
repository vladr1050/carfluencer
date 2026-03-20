<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\DashboardMetricsServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsServiceInterface $dashboardMetrics,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->dashboardMetrics->advertiserSummary($request->user()));
    }
}
