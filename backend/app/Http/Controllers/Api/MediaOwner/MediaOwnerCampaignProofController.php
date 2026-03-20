<?php

namespace App\Http\Controllers\Api\MediaOwner;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProof;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaOwnerCampaignProofController extends Controller
{
    /**
     * List proofs for this campaign that belong to the media owner's vehicles.
     */
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $ownedIds = Vehicle::query()
            ->where('media_owner_id', $request->user()->id)
            ->pluck('id');

        $proofs = CampaignProof::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('vehicle_id', $ownedIds)
            ->with('vehicle:id,brand,model,imei')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $this->proofRows($proofs)]);
    }

    /**
     * Upload a proof file for a campaign vehicle owned by the authenticated media owner.
     */
    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $user = $request->user();
        $ownedVehicleIds = Vehicle::query()
            ->where('media_owner_id', $user->id)
            ->pluck('id');

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        abort_unless($ownedVehicleIds->contains((int) $data['vehicle_id']), 403);

        $onCampaign = $campaign->vehicles()->where('vehicles.id', $data['vehicle_id'])->exists();

        abort_unless($onCampaign, 422, 'This vehicle is not linked to the selected campaign.');

        $path = $request->file('file')->store('campaign-proofs', 'public');

        $proof = CampaignProof::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $data['vehicle_id'],
            'uploaded_by_user_id' => $user->id,
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
