<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserProfile;
use App\Models\MediaOwnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_MEDIA_OWNER, User::ROLE_ADVERTISER])],
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'company_name' => $data['company_name'],
            'status' => 'active',
        ]);

        if ($user->isMediaOwner()) {
            MediaOwnerProfile::query()->create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'phone' => $data['phone'] ?? null,
            ]);
        } else {
            AdvertiserProfile::query()->create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'phone' => $data['phone'] ?? null,
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            abort(403, 'Account is not active.');
        }

        $user->tokens()->delete();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    private function userPayload(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $user->loadMissing(['mediaOwnerProfile', 'advertiserProfile']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_name' => $user->company_name,
            'status' => $user->status,
            'media_owner_profile' => $user->mediaOwnerProfile,
            'advertiser_profile' => $user->advertiserProfile,
        ];
    }
}
