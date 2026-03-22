<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
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

    public function test_campaign_scope_without_campaign_id_logs_autoload_skip_and_does_not_keep_vehicle_id_from_url(): void
    {
        Event::fake([MessageLogged::class]);

        $admin = User::factory()->admin()->create();

        $url = '/admin/telemetry-heatmap-page?'
            .'form%5Bmap_scope%5D=campaign&'
            .'form%5Bvehicle_id%5D=9&'
            .'form%5Bmotion%5D=both&'
            .'form%5Bdate_from%5D=2026-03-15&'
            .'form%5Bdate_to%5D=2026-03-22';

        $response = $this->actingAs($admin)->get($url);

        $response->assertOk();

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'warning'
                && $e->message === 'telemetry_heatmap_autoload_skipped'
                && ($e->context['map_scope'] ?? null) === 'campaign'
                && ($e->context['has_campaign_id'] ?? true) === false;
        });
    }
}
