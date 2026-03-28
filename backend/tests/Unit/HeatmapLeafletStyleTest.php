<?php

namespace Tests\Unit;

use App\Services\Telemetry\HeatmapLeafletStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HeatmapLeafletStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_driving_and_parking_use_distinct_gradients_and_denoms(): void
    {
        Config::set('reports.heatmap_export.shadow_preset', 'current');

        $d = HeatmapLeafletStyle::heatLayerOptionsForExport('driving');
        $p = HeatmapLeafletStyle::heatLayerOptionsForExport('parking');

        $this->assertNotSame($d['gradient']['0'], $p['gradient']['0']);
        $this->assertNotEquals($d['max'], $p['max']);
        $this->assertNotEquals($d['radius'], $p['radius']);
    }

    public function test_driving_export_uses_same_moving_layer_as_advertiser_portal(): void
    {
        Config::set('reports.heatmap_export.shadow_preset', 'xsmall');

        $d = HeatmapLeafletStyle::heatLayerOptionsForExport('driving');
        $this->assertSame(8, $d['radius']);
        $this->assertSame(3, $d['blur']);
        $this->assertEqualsWithDelta(1.0 / 2.15, $d['max'], 0.0001);
        $this->assertSame('#440154', $d['gradient']['0'] ?? '');
    }

    public function test_tile_layer_matches_maptiler_when_key_set(): void
    {
        config(['services.maptiler.api_key' => 'k']);

        $t = HeatmapLeafletStyle::tileLayerConfig();
        $this->assertStringContainsString('maptiler.com', $t['url']);
        $this->assertNull($t['subdomains']);
    }

    public function test_shadow_preset_falls_back_to_telemetry_default(): void
    {
        Config::set('reports.heatmap_export.shadow_preset', null);
        Config::set('telemetry.heatmap.global_default_shadow', 'small');

        $this->assertSame('small', HeatmapLeafletStyle::shadowPresetForReport());
    }
}
