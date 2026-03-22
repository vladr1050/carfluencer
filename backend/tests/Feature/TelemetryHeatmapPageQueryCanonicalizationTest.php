<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryHeatmapPageQueryCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_strips_php_form_dot_aliases_when_form_bracket_array_present(): void
    {
        $admin = User::factory()->admin()->create();

        $dirty = '/admin/telemetry-heatmap-page?'
            .'form_map_scope=campaign&form_motion=both&'
            .'form%5Bmap_scope%5D=vehicle&form%5Bvehicle_id%5D=9&form%5Bmotion%5D=both';

        $response = $this->actingAs($admin)->get($dirty);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('form_map_scope', $location);
        $this->assertStringNotContainsString('form_motion', $location);
        $this->assertStringContainsString('form%5Bmap_scope%5D=vehicle', $location);
        $this->assertStringContainsString('form%5Bvehicle_id%5D=9', $location);
    }
}
