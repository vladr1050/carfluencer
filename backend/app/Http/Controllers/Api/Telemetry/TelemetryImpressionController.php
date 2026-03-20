<?php

namespace App\Http\Controllers\Api\Telemetry;

use App\Http\Controllers\Controller;
use App\Models\DailyImpression;
use App\Models\DailyZoneImpression;
use App\Services\Telemetry\TelemetryGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryImpressionController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdvertiser(), 403);

        $data = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $q = DailyImpression::query()
            ->with(['campaign:id,name', 'vehicle:id,brand,model,imei'])
            ->whereHas('campaign', fn ($c) => $c->where('advertiser_id', $request->user()->id));

        if (! empty($data['campaign_id'])) {
            abort_unless(
                TelemetryGate::advertiserOwnsCampaign($request->user(), (int) $data['campaign_id']),
                403
            );
            $q->where('campaign_id', (int) $data['campaign_id']);
        }

        if (! empty($data['date_from'])) {
            $q->whereDate('stat_date', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $q->whereDate('stat_date', '<=', $data['date_to']);
        }

        $rows = $q->orderByDesc('stat_date')->limit(2_000)->get()->map(fn (DailyImpression $r) => [
            'stat_date' => $r->stat_date?->toDateString(),
            'campaign_id' => $r->campaign_id,
            'campaign_name' => $r->campaign?->name,
            'vehicle_id' => $r->vehicle_id,
            'vehicle_label' => $r->vehicle ? trim($r->vehicle->brand.' '.$r->vehicle->model) : null,
            'imei' => $r->vehicle?->imei,
            'impressions' => $r->impressions,
            'driving_distance_km' => $r->driving_distance_km !== null ? (float) $r->driving_distance_km : null,
            'parking_minutes' => $r->parking_minutes,
        ]);

        return response()->json(['data' => $rows]);
    }

    public function zones(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdvertiser(), 403);

        $data = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $q = DailyZoneImpression::query()
            ->with(['zone:id,code,name', 'campaign:id,name'])
            ->whereHas('campaign', fn ($c) => $c->where('advertiser_id', $request->user()->id));

        if (! empty($data['campaign_id'])) {
            abort_unless(
                TelemetryGate::advertiserOwnsCampaign($request->user(), (int) $data['campaign_id']),
                403
            );
            $q->where('campaign_id', (int) $data['campaign_id']);
        }

        if (! empty($data['date_from'])) {
            $q->whereDate('stat_date', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $q->whereDate('stat_date', '<=', $data['date_to']);
        }

        $rows = $q->orderByDesc('stat_date')->limit(2_000)->get()->map(fn (DailyZoneImpression $r) => [
            'stat_date' => $r->stat_date?->toDateString(),
            'zone_id' => $r->zone_id,
            'zone_code' => $r->zone?->code,
            'zone_name' => $r->zone?->name,
            'campaign_id' => $r->campaign_id,
            'campaign_name' => $r->campaign?->name,
            'impressions' => $r->impressions,
        ]);

        return response()->json(['data' => $rows]);
    }
}
