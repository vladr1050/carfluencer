<?php

namespace Tests\Unit;

use App\Services\Analytics\CampaignInsightsService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CampaignInsightsServiceTest extends TestCase
{
    private CampaignInsightsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CampaignInsightsService;
        Config::set('reports.insights.exposure.parking_dominant_min', 0.75);
        Config::set('reports.insights.exposure.balanced_min', 0.40);
        Config::set('reports.insights.location.highly_concentrated_top1_min', 0.50);
        Config::set('reports.insights.location.highly_concentrated_top3_min', 0.75);
        Config::set('reports.insights.location.moderately_concentrated_top3_min', 0.50);
    }

    public function test_insufficient_data_shape(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 0,
                'driving_hours' => 0.0,
                'parking_hours' => 0.0,
            ],
            'exposure_split' => ['driving_share' => 0.0, 'parking_share' => 0.0],
            'top_locations' => [],
        ]);

        $this->assertStringContainsString('Insufficient data', (string) $out['summary']);
        $this->assertSame([], $out['highlights']);
        $this->assertNull($out['exposure_pattern']);
        $this->assertNull($out['location_pattern']);
    }

    public function test_parking_dominant_and_highly_concentrated(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 100,
                'driving_hours' => 1.0,
                'parking_hours' => 9.0,
            ],
            'exposure_split' => ['driving_share' => 0.1, 'parking_share' => 0.9],
            'top_locations' => [
                ['dwell_proxy' => 800, 'label' => 'Riga Center'],
                ['dwell_proxy' => 100, 'label' => null],
                ['dwell_proxy' => 100, 'label' => null],
            ],
        ]);

        $this->assertSame('parking_dominant', $out['exposure_pattern']);
        $this->assertSame('highly_concentrated', $out['location_pattern']);
        $this->assertNotNull($out['summary']);
        $this->assertStringContainsString('parked', strtolower($out['summary']));
        $this->assertGreaterThanOrEqual(2, count($out['highlights']));
        $this->assertLessThanOrEqual(4, count($out['highlights']));
    }

    public function test_movement_dominant_and_distributed(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 50,
                'driving_hours' => 8.0,
                'parking_hours' => 1.0,
            ],
            'exposure_split' => ['driving_share' => 0.89, 'parking_share' => 0.11],
            'top_locations' => array_map(
                static fn () => ['dwell_proxy' => 100, 'label' => null],
                range(1, 10)
            ),
        ]);

        $this->assertSame('movement_dominant', $out['exposure_pattern']);
        $this->assertSame('distributed', $out['location_pattern']);
        $this->assertStringContainsString('movement', strtolower($out['summary']));
    }

    public function test_balanced_and_moderate_concentration(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 10,
                'driving_hours' => 3.0,
                'parking_hours' => 3.0,
            ],
            'exposure_split' => ['driving_share' => 0.5, 'parking_share' => 0.5],
            'top_locations' => [
                ['dwell_proxy' => 250, 'label' => 'Zone A'],
                ['dwell_proxy' => 240, 'label' => 'Zone B'],
                ['dwell_proxy' => 230, 'label' => 'Zone C'],
                ['dwell_proxy' => 220, 'label' => null],
                ['dwell_proxy' => 210, 'label' => null],
            ],
        ]);

        $this->assertSame('balanced', $out['exposure_pattern']);
        $this->assertSame('moderately_concentrated', $out['location_pattern']);
    }

    public function test_empty_top_locations_still_classifies_exposure(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 1,
                'driving_hours' => 2.0,
                'parking_hours' => 8.0,
            ],
            'exposure_split' => ['driving_share' => 0.2, 'parking_share' => 0.8],
            'top_locations' => [],
        ]);

        $this->assertSame('parking_dominant', $out['exposure_pattern']);
        $this->assertNull($out['location_pattern']);
    }

    public function test_zero_dwell_locations_yield_null_location_pattern_without_error(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 5,
                'driving_hours' => 1.0,
                'parking_hours' => 1.0,
            ],
            'exposure_split' => ['driving_share' => 0.5, 'parking_share' => 0.5],
            'top_locations' => [
                ['dwell_proxy' => 0],
                ['dwell_proxy' => 0],
            ],
        ]);

        $this->assertNull($out['location_pattern']);
    }
}
