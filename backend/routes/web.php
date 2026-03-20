<?php

use App\Http\Controllers\Admin\AdminTelemetryHeatmapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/internal/admin/telemetry/heatmap-data', [AdminTelemetryHeatmapController::class, 'data'])
        ->name('internal.admin.telemetry.heatmap-data');
});
