<?php

use App\Http\Controllers\Api\Advertiser\AdvertiserCampaignController;
use App\Http\Controllers\Api\Advertiser\AdvertiserCampaignProofController;
use App\Http\Controllers\Api\Advertiser\AdvertiserCampaignVehicleController;
use App\Http\Controllers\Api\Advertiser\AdvertiserDashboardController;
use App\Http\Controllers\Api\Advertiser\AdvertiserHeatmapController;
use App\Http\Controllers\Api\Advertiser\AdvertiserMapBasemapController;
use App\Http\Controllers\Api\Advertiser\AdvertiserPricingController;
use App\Http\Controllers\Api\Advertiser\AdvertiserProfileController;
use App\Http\Controllers\Api\Advertiser\AdvertiserVehicleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentBlockController;
use App\Http\Controllers\Api\MediaOwner\MediaOwnerCampaignController;
use App\Http\Controllers\Api\MediaOwner\MediaOwnerCampaignProofController;
use App\Http\Controllers\Api\MediaOwner\MediaOwnerDashboardController;
use App\Http\Controllers\Api\MediaOwner\MediaOwnerEarningsController;
use App\Http\Controllers\Api\MediaOwner\MediaOwnerVehicleController;
use App\Http\Controllers\Api\Telemetry\TelemetryFleetController;
use App\Http\Controllers\Api\Telemetry\TelemetryImpressionController;
use App\Http\Controllers\Api\Telemetry\TelemetryLocationController;
use App\Http\Controllers\Api\VehicleMetaController;
use Illuminate\Support\Facades\Route;

Route::get('content-blocks', [ContentBlockController::class, 'index']);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('meta/vehicle-fields', [VehicleMetaController::class, 'show']);

    Route::prefix('media-owner')->middleware('role:media_owner')->group(function (): void {
        Route::get('dashboard', [MediaOwnerDashboardController::class, 'show']);
        Route::get('vehicles', [MediaOwnerVehicleController::class, 'index']);
        Route::post('vehicles', [MediaOwnerVehicleController::class, 'store']);
        Route::get('vehicles/{vehicle}', [MediaOwnerVehicleController::class, 'show']);
        Route::put('vehicles/{vehicle}', [MediaOwnerVehicleController::class, 'update']);
        Route::post('vehicles/{vehicle}/image', [MediaOwnerVehicleController::class, 'uploadImage']);
        Route::get('campaigns', [MediaOwnerCampaignController::class, 'index']);
        Route::get('campaigns/{campaign}', [MediaOwnerCampaignController::class, 'show']);
        Route::get('campaigns/{campaign}/proofs', [MediaOwnerCampaignProofController::class, 'index']);
        Route::post('campaigns/{campaign}/proofs', [MediaOwnerCampaignProofController::class, 'store']);
        Route::get('earnings', [MediaOwnerEarningsController::class, 'index']);
    });

    Route::prefix('advertiser')->middleware('role:advertiser')->group(function (): void {
        Route::get('dashboard', [AdvertiserDashboardController::class, 'show']);
        Route::get('campaigns', [AdvertiserCampaignController::class, 'index']);
        Route::post('campaigns', [AdvertiserCampaignController::class, 'store']);
        Route::get('campaigns/{campaign}', [AdvertiserCampaignController::class, 'show']);
        Route::put('campaigns/{campaign}', [AdvertiserCampaignController::class, 'update']);
        Route::post('campaigns/{campaign}/vehicles', [AdvertiserCampaignVehicleController::class, 'store']);
        Route::delete('campaigns/{campaign}/vehicles/{campaignVehicle}', [AdvertiserCampaignVehicleController::class, 'destroy']);
        Route::get('campaigns/{campaign}/proofs', [AdvertiserCampaignProofController::class, 'index']);
        Route::get('vehicles', [AdvertiserVehicleController::class, 'index']);
        Route::get('vehicles/{vehicle}', [AdvertiserVehicleController::class, 'show']);
        Route::get('heatmap', [AdvertiserHeatmapController::class, 'show']);
        Route::get('map-basemap', AdvertiserMapBasemapController::class);
        Route::get('pricing', [AdvertiserPricingController::class, 'index']);
        Route::get('profile-discounts', [AdvertiserProfileController::class, 'discounts']);
    });

    Route::prefix('telemetry')->group(function (): void {
        Route::get('locations/raw', [TelemetryLocationController::class, 'raw']);
        Route::get('impressions/daily', [TelemetryImpressionController::class, 'daily']);
        Route::get('impressions/zones', [TelemetryImpressionController::class, 'zones']);
        Route::get('vehicles', [TelemetryFleetController::class, 'vehicles']);
        Route::get('campaigns', [TelemetryFleetController::class, 'campaigns']);
    });
});
