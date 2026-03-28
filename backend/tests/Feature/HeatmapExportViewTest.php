<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class HeatmapExportViewTest extends TestCase
{
    public function test_driving_export_template_includes_activity_legend_and_heat(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => 'driving_heat',
            'heatData' => [],
            'hotspots' => [],
            'modeLabel' => 'Driving',
            'viewportLabel' => 'Full',
            'periodLabel' => '2026-03-01 — 2026-03-31',
            'vehicleCount' => 1,
            'viewport' => ['id' => 'full', 'label' => 'Full', 'fit_to_data' => true],
            'tileLayer' => [
                'url' => 'https://example.test/tiles/{z}/{x}/{y}.png',
                'attribution' => '',
                'subdomains' => null,
                'max_zoom' => 19,
            ],
            'heatLayerOptions' => [
                'radius' => 14,
                'blur' => 24,
                'maxZoom' => 14,
                'minOpacity' => 0.42,
                'max' => 1.0,
                'gradient' => ['0' => '#2c7bb6', '1' => '#d73027'],
            ],
        ])->render();

        $this->assertStringContainsString('Low', $html);
        $this->assertStringContainsString('High', $html);
        $this->assertStringContainsString('leaflet.heat', $html);
        $this->assertStringContainsString('hotspots', $html);
    }

    public function test_parking_export_uses_density_legend_and_heat(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => 'parking_heat',
            'heatData' => [[56.95, 24.1, 0.5]],
            'hotspots' => [
                ['lat' => 56.95, 'lng' => 24.1, 'title' => 'Zone A', 'subtitle' => 'Relative dwell: 100%'],
            ],
            'modeLabel' => 'Parking',
            'viewportLabel' => 'Full',
            'periodLabel' => '2026-03-01 — 2026-03-31',
            'vehicleCount' => 1,
            'viewport' => ['id' => 'full', 'label' => 'Full', 'fit_to_data' => true],
            'tileLayer' => [
                'url' => 'https://example.test/tiles/{z}/{x}/{y}.png',
                'attribution' => '',
                'subdomains' => null,
                'max_zoom' => 19,
            ],
            'heatLayerOptions' => [
                'radius' => 26,
                'blur' => 30,
                'maxZoom' => 15,
                'minOpacity' => 0.38,
                'max' => 1.0,
                'gradient' => ['0' => '#edf8fb', '1' => '#006d2c'],
            ],
        ])->render();

        $this->assertStringContainsString('Short stay', $html);
        $this->assertStringContainsString('Long stay', $html);
        $this->assertStringContainsString('leaflet.heat', $html);
        $this->assertStringNotContainsString('circleMarker', $html);
    }
}
