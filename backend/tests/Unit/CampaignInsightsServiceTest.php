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

    /**
     * @return array<string, mixed>
     */
    private function baseParkingByZone(int $windowMin, array $byZone): array
    {
        return [
            'totals' => [
                'parking_minutes_in_window' => $windowMin,
                'parking_sessions_in_window' => 0,
                'vehicles' => 0,
            ],
            'by_zone' => $byZone,
            'unattributed' => ['parking_minutes' => 0, 'sessions_count' => 0],
        ];
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
            'parking_by_zone' => $this->baseParkingByZone(0, []),
        ]);

        $this->assertStringContainsString('Insufficient data', (string) $out['summary']);
        $this->assertSame([], $out['highlights']);
        $this->assertNull($out['exposure_pattern']);
        $this->assertNull($out['location_pattern']);
    }

    public function test_parking_dominant_and_highly_concentrated_from_geo_zones(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 100,
                'driving_hours' => 1.0,
                'parking_hours' => 9.0,
            ],
            'exposure_split' => ['driving_share' => 0.1, 'parking_share' => 0.9],
            'parking_by_zone' => $this->baseParkingByZone(1000, [
                ['name' => 'Riga Center', 'code' => 'RC', 'parking_minutes' => 800],
                ['name' => 'Other A', 'code' => 'A', 'parking_minutes' => 100],
                ['name' => 'Other B', 'code' => 'B', 'parking_minutes' => 100],
            ]),
        ]);

        $this->assertSame('parking_dominant', $out['exposure_pattern']);
        $this->assertSame('highly_concentrated', $out['location_pattern']);
        $this->assertNotNull($out['summary']);
        $this->assertStringContainsString('parked', strtolower($out['summary']));
        $this->assertStringContainsString('GeoZone', $out['summary']);
        $this->assertGreaterThanOrEqual(2, count($out['highlights']));
        $this->assertLessThanOrEqual(4, count($out['highlights']));
    }

    public function test_movement_dominant_and_distributed(): void
    {
        $zones = [];
        foreach (range(1, 10) as $i) {
            $zones[] = ['name' => 'Zone '.$i, 'code' => 'Z'.$i, 'parking_minutes' => 100];
        }

        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 50,
                'driving_hours' => 8.0,
                'parking_hours' => 1.0,
            ],
            'exposure_split' => ['driving_share' => 0.89, 'parking_share' => 0.11],
            'parking_by_zone' => $this->baseParkingByZone(1000, $zones),
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
            'parking_by_zone' => $this->baseParkingByZone(1150, [
                ['name' => 'Zone A', 'code' => 'A', 'parking_minutes' => 250],
                ['name' => 'Zone B', 'code' => 'B', 'parking_minutes' => 240],
                ['name' => 'Zone C', 'code' => 'C', 'parking_minutes' => 230],
                ['name' => 'Zone D', 'code' => 'D', 'parking_minutes' => 220],
                ['name' => 'Zone E', 'code' => 'E', 'parking_minutes' => 210],
            ]),
        ]);

        $this->assertSame('balanced', $out['exposure_pattern']);
        $this->assertSame('moderately_concentrated', $out['location_pattern']);
    }

    public function test_no_geo_zone_breakdown_still_classifies_exposure(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 1,
                'driving_hours' => 2.0,
                'parking_hours' => 8.0,
            ],
            'exposure_split' => ['driving_share' => 0.2, 'parking_share' => 0.8],
            'parking_by_zone' => $this->baseParkingByZone(0, []),
        ]);

        $this->assertSame('parking_dominant', $out['exposure_pattern']);
        $this->assertNull($out['location_pattern']);
    }

    public function test_zero_minute_zones_yield_null_location_pattern(): void
    {
        $out = $this->svc->buildInsights([
            'kpis' => [
                'impressions' => 5,
                'driving_hours' => 1.0,
                'parking_hours' => 1.0,
            ],
            'exposure_split' => ['driving_share' => 0.5, 'parking_share' => 0.5],
            'parking_by_zone' => $this->baseParkingByZone(0, [
                ['name' => 'Z1', 'code' => '1', 'parking_minutes' => 0],
                ['name' => 'Z2', 'code' => '2', 'parking_minutes' => 0],
            ]),
        ]);

        $this->assertNull($out['location_pattern']);
    }
}
