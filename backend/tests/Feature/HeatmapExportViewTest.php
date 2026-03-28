<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class HeatmapExportViewTest extends TestCase
{
    public function test_driving_export_template_includes_activity_legend(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'driving_heat',
            'heatData' => [],
            'parkingCircles' => [],
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
                'radius' => 25,
                'blur' => 15,
                'maxZoom' => 17,
                'minOpacity' => 0.85,
                'max' => 1.0,
                'gradient' => ['0' => '#2E7D32', '1' => '#D32F2F'],
            ],
        ])->render();

        $this->assertStringContainsString('Low activity', $html);
        $this->assertStringContainsString('Top activity zones', $html);
        $this->assertStringContainsString('leaflet.heat', $html);
    }

    public function test_parking_circles_export_has_legend_not_leaflet_heat(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'parking_circles',
            'heatData' => [],
            'parkingCircles' => [
                [
                    'lat' => 56.95,
                    'lng' => 24.1,
                    'radius_px' => 12.0,
                    'fillColor' => '#8E24AA',
                    'fillOpacity' => 0.72,
                    'weight' => 2,
                    'color' => '#333',
                    'label' => 1,
                    'tooltip' => 'Parking intensity: 100',
                ],
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
            'heatLayerOptions' => [],
        ])->render();

        $this->assertStringContainsString('Circle size = parking intensity', $html);
        $this->assertStringNotContainsString('leaflet.heat', $html);
        $this->assertStringContainsString('circleMarker', $html);
    }
}
