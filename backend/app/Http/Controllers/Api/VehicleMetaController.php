<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleMetaController extends Controller
{
    /**
     * Standard colors + fleet statuses for SPA forms (media owner / advertiser read-only labels).
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && ($user->isMediaOwner() || $user->isAdvertiser()),
            403
        );

        $colors = collect(config('vehicle.colors', []))
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values();

        $statuses = collect(config('vehicle.fleet_statuses', []))
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values();

        return response()->json([
            'colors' => $colors,
            'fleet_statuses' => $statuses,
            'catalog_statuses' => Vehicle::catalogVisibleStatuses(),
        ]);
    }
}
