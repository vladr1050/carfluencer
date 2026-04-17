<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryBackfillDailyPipelineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_from_and_to(): void
    {
        $this->artisan('telemetry:backfill-daily-pipeline')
            ->assertFailed();
    }

    public function test_rejects_from_after_to(): void
    {
        $this->artisan('telemetry:backfill-daily-pipeline', [
            '--from' => '2024-02-01',
            '--to' => '2024-01-01',
        ])->assertFailed();
    }

    public function test_rejects_both_skip_flags(): void
    {
        $this->artisan('telemetry:backfill-daily-pipeline', [
            '--from' => '2024-01-01',
            '--to' => '2024-01-01',
            '--skip-sessions' => true,
            '--skip-aggregate' => true,
        ])->assertFailed();
    }
}
