<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Services\Pricing\PlacementPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdvertiserCampaignVehicleController extends Controller
{
    public function __construct(
        private PlacementPricingService $placementPricing,
    ) {}

    /**
     * Attach a vehicle to the advertiser's campaign with an ad placement size class.
     */
    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'placement_size_class' => ['required', 'string', Rule::in(['S', 'M', 'L', 'XL'])],
            'agreed_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $exists = CampaignVehicle::query()
            ->where('campaign_id', $campaign->id)
            ->where('vehicle_id', $data['vehicle_id'])
            ->exists();

        abort_if($exists, 422, 'Vehicle is already linked to this campaign.');

        $agreed = $data['agreed_price'] ?? null;
        if ($agreed === null) {
            $agreed = $this->placementPricing->resolveBasePrice($data['placement_size_class']);
        }

        $row = CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $data['vehicle_id'],
            'placement_size_class' => $data['placement_size_class'],
            'agreed_price' => $agreed,
            'status' => 'pending',
        ]);

        $row->load('vehicle');

        return response()->json($row, 201);
    }

    public function destroy(Request $request, Campaign $campaign, CampaignVehicle $campaignVehicle): JsonResponse
    {
        abort_if($campaignVehicle->campaign_id !== $campaign->id, 404);

        $this->authorize('delete', $campaignVehicle);

        $campaignVehicle->delete();

        return response()->json(null, 204);
    }

}
