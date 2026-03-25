<?php

namespace App\Http\Controllers\Api\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
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
     * @param  Collection<int, CampaignProof>  $proofs
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
