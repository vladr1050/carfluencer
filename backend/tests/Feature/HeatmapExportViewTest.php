<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class HeatmapExportViewTest extends TestCase
{
    public function test_driving_export_template_matches_portal_legend(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => 'driving_heat',
            'heatData' => [],
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
                'radius' => 24,
                'blur' => 14,
                'maxZoom' => 17,
                'minOpacity' => 0.16,
                'max' => 1.0 / 2.15,
                'gradient' => ['0' => '#440154', '1' => '#fde725'],
            ],
        ])->render();

        $this->assertStringContainsString('same as advertiser portal', $html);
        $this->assertStringContainsString('leaflet.heat', $html);
        $this->assertStringContainsString('#440154', $html);
        $this->assertStringContainsString('heatmapFitBoundsPadding', $html);
    }

    public function test_parking_export_template_matches_portal_legend(): void
    {
        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => 'parking_heat',
            'heatData' => [[56.95, 24.1, 0.5]],
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
                'radius' => 40,
                'blur' => 21,
                'maxZoom' => 17,
                'minOpacity' => 0.27,
                'max' => 1.0 / 1.82,
                'gradient' => ['0' => '#1b5e20', '1' => '#c62828'],
            ],
        ])->render();

        $this->assertStringContainsString('same as advertiser portal', $html);
        $this->assertStringContainsString('leaflet.heat', $html);
        $this->assertStringNotContainsString('circleMarker', $html);
        $this->assertStringContainsString('heatmapFitBoundsPadding', $html);
    }
}
