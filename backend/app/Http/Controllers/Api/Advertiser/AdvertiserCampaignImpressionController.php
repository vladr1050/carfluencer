<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\ImpressionEngine\CampaignImpressionSnapshotResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserCampaignImpressionController extends Controller
{
    public function __construct(
        private readonly CampaignImpressionSnapshotResolver $impressionSnapshots,
    ) {}

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAnalytics', $campaign);

        $data = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $stat = $this->impressionSnapshots->findLatestDone(
            (int) $campaign->id,
            (string) $data['date_from'],
            (string) $data['date_to'],
        );

        if ($stat === null) {
            return response()->json([
                'message' => 'No impression snapshot for this period. Run calculation from admin or queue.',
            ], 404);
        }

        return response()->json([
            'campaign_id' => $campaign->id,
            'date_from' => $stat->date_from->format('Y-m-d'),
            'date_to' => $stat->date_to->format('Y-m-d'),
            'vehicles_count' => $stat->vehicles_count,
            'driving_impressions' => $stat->driving_impressions,
            'parking_impressions' => $stat->parking_impressions,
            'total_gross_impressions' => $stat->total_gross_impressions,
            'campaign_price' => (float) $stat->campaign_price,
            'cpm' => $stat->cpm === null ? null : (float) $stat->cpm,
            'calculation_version' => $stat->calculation_version,
            'mobility_data_version' => $stat->mobility_data_version,
            'coefficients_version' => $stat->coefficients_version,
            'telemetry_sampling_seconds' => $stat->telemetry_sampling_seconds,
            'matched_direct_count' => $stat->matched_direct_count,
            'matched_fallback_count' => $stat->matched_fallback_count,
            'unmatched_count' => $stat->unmatched_count,
            'created_at' => $stat->created_at?->toIso8601String(),
        ]);
    }
}
