<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Telemetry\HeatmapDataServiceInterface::class,
            function ($app) {
                return config('telemetry.heatmap.driver') === 'database'
                    ? $app->make(\App\Services\Telemetry\DatabaseHeatmapDataService::class)
                    : $app->make(\App\Services\Telemetry\MockHeatmapDataService::class);
            }
        );

        $this->app->singleton(\App\Services\Telemetry\MockDashboardMetricsService::class);

        $this->app->bind(
            \App\Services\Telemetry\DashboardMetricsServiceInterface::class,
            \App\Services\Telemetry\HttpDashboardMetricsService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
