<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdvertiserCampaignProofController extends Controller
{
    /**
     * List proofs uploaded for this campaign.
     */
    public function index(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $proofs = $campaign->proofs()
            ->with('vehicle:id,brand,model,imei')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $this->proofRows($proofs)]);
    }

    /**
     * Upload a proof file for a vehicle that is linked to the advertiser's campaign.
     */
    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $onCampaign = $campaign->vehicles()->where('vehicles.id', $data['vehicle_id'])->exists();

        abort_unless($onCampaign, 422, 'This vehicle is not linked to the selected campaign.');

        $path = $request->file('file')->store('campaign-proofs', 'public');

        $proof = CampaignProof::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $data['vehicle_id'],
            'uploaded_by_user_id' => $request->user()->id,
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        return response()->json([
            'id' => $proof->id,
            'campaign_id' => $proof->campaign_id,
            'vehicle_id' => $proof->vehicle_id,
            'file_path' => $proof->file_path,
            'url' => Storage::disk('public')->url($proof->file_path),
            'status' => $proof->status,
        ], 201);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CampaignProof>  $proofs
     * @return list<array<string, mixed>>
     */
    private function proofRows($proofs): array
    {
        return $proofs->map(function (CampaignProof $p): array {
            $v = $p->vehicle;

            return [
                'id' => $p->id,
                'vehicle_id' => $p->vehicle_id,
                'status' => $p->status,
                'comment' => $p->comment,
                'url' => Storage::disk('public')->url($p->file_path),
                'created_at' => $p->created_at?->toIso8601String(),
                'vehicle' => $v ? [
                    'id' => $v->id,
                    'brand' => $v->brand,
                    'model' => $v->model,
                    'imei' => $v->imei,
                ] : null,
            ];
        })->values()->all();
    }
}
