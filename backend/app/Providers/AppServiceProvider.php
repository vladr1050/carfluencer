<?php

namespace App\Providers;

use App\Models\CampaignVehicle;
use App\Observers\CampaignVehicleObserver;
use App\Services\Telemetry\DashboardMetricsServiceInterface;
use App\Services\Telemetry\DatabaseHeatmapDataService;
use App\Services\Telemetry\HeatmapDataServiceInterface;
use App\Services\Telemetry\HttpDashboardMetricsService;
use App\Services\Telemetry\MockDashboardMetricsService;
use App\Services\Telemetry\MockHeatmapDataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            HeatmapDataServiceInterface::class,
            function ($app) {
                return config('telemetry.heatmap.driver') === 'database'
                    ? $app->make(DatabaseHeatmapDataService::class)
                    : $app->make(MockHeatmapDataService::class);
            }
        );

        $this->app->singleton(MockDashboardMetricsService::class);

        $this->app->bind(
            DashboardMetricsServiceInterface::class,
            HttpDashboardMetricsService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CampaignVehicle::observe(CampaignVehicleObserver::class);
    }
}
