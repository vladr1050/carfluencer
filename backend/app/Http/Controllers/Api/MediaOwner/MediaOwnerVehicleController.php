<?php

namespace App\Http\Controllers\Api\MediaOwner;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaOwnerVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->where('media_owner_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $vehicles]);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json($vehicle);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'color' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'imei' => ['required', 'string', 'max:64', 'unique:vehicles,imei'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $vehicle = Vehicle::query()->create([
            ...$data,
            'media_owner_id' => $request->user()->id,
            'quantity' => $data['quantity'] ?? 1,
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json($vehicle, 201);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'brand' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'color' => ['nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'imei' => ['sometimes', 'string', 'max:64', 'unique:vehicles,imei,'.$vehicle->id],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:32'],
        ]);

        $vehicle->update($data);

        return response()->json($vehicle);
    }

    public function uploadImage(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        if ($vehicle->image_path) {
            Storage::disk('public')->delete($vehicle->image_path);
        }

        $path = $request->file('image')->store('vehicles', 'public');
        $vehicle->update(['image_path' => $path]);
        $vehicle->refresh();

        return response()->json([
            'image_path' => $vehicle->image_path,
            'url' => Storage::disk('public')->url($vehicle->image_path),
        ]);
    }

}
