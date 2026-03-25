<?php

namespace Tests\Feature;

use App\Models\TelemetrySyncEvent;
use App\Services\Telemetry\TelemetrySyncEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetrySyncTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_persists_event(): void
    {
        TelemetrySyncEventRecorder::record(
            TelemetrySyncEvent::SOURCE_SCHEDULER,
            TelemetrySyncEvent::ACTION_INCREMENTAL_PULL,
            TelemetrySyncEvent::STATUS_SUCCESS,
            'Test incremental',
            ['imeis' => ['111'], 'rows' => 3],
        );

        $this->assertDatabaseHas('telemetry_sync_events', [
            'source' => TelemetrySyncEvent::SOURCE_SCHEDULER,
            'action' => TelemetrySyncEvent::ACTION_INCREMENTAL_PULL,
            'status' => TelemetrySyncEvent::STATUS_SUCCESS,
        ]);
    }
}
