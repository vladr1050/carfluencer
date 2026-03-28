<?php

namespace App\Providers;

use App\Models\CampaignVehicle;
use App\Observers\CampaignVehicleObserver;
use App\Services\Analytics\Contracts\LocationLabelProviderInterface;
use App\Services\Analytics\NominatimLocationLabelProvider;
use App\Services\Analytics\NullLocationLabelProvider;
use App\Services\Analytics\TopLocationLabelResolver;
use App\Services\Reports\BrowsershotCampaignReportPdfService;
use App\Services\Reports\BrowsershotHeatmapImageService;
use App\Services\Reports\CampaignReportMetricsService;
use App\Services\Reports\Contracts\CampaignReportMetricsServiceInterface;
use App\Services\Reports\Contracts\CampaignReportPdfServiceInterface;
use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use App\Services\Reports\FakeCampaignReportPdfService;
use App\Services\Reports\FakeHeatmapImageService;
use App\Services\Telemetry\DashboardMetricsServiceInterface;
use App\Services\Telemetry\DatabaseDashboardMetricsService;
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
        $this->app->singleton(CampaignReportMetricsServiceInterface::class, CampaignReportMetricsService::class);

        $this->app->bind(LocationLabelProviderInterface::class, function () {
            $p = strtolower(trim((string) config('reports.location_labels.provider', 'none')));

            return match ($p) {
                'nominatim' => new NominatimLocationLabelProvider,
                default => new NullLocationLabelProvider,
            };
        });

        $this->app->singleton(TopLocationLabelResolver::class);

        $this->app->bind(HeatmapImageServiceInterface::class, function ($app) {
            if (config('reports.browser_driver') === 'fake') {
                return new FakeHeatmapImageService;
            }

            return $app->make(BrowsershotHeatmapImageService::class);
        });

        $this->app->bind(CampaignReportPdfServiceInterface::class, function ($app) {
            if (config('reports.browser_driver') === 'fake') {
                return new FakeCampaignReportPdfService;
            }

            return $app->make(BrowsershotCampaignReportPdfService::class);
        });

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
            function ($app) {
                $url = config('telemetry.metrics_url');

                return is_string($url) && $url !== ''
                    ? $app->make(HttpDashboardMetricsService::class)
                    : $app->make(DatabaseDashboardMetricsService::class);
            }
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
