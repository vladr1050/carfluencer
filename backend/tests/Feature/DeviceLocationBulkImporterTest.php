<?php

namespace Tests\Feature;

use App\Models\DeviceLocation;
use App\Services\Telemetry\DeviceLocationBulkImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceLocationBulkImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_does_not_fail_when_batch_contains_duplicate_device_id_and_event_at(): void
    {
        $ts = 1_712_102_400; // 2024-03-03 00:00:00 UTC
        $rows = [
            [
                'device_id' => '863540060109445',
                'timestamp' => $ts,
                'latitude' => 51.1,
                'longitude' => -0.1,
                'speed' => 10,
            ],
            [
                'device_id' => '863540060109445',
                'timestamp' => $ts,
                'latitude' => 51.2,
                'longitude' => -0.2,
                'speed' => 20,
            ],
        ];

        $importer = new DeviceLocationBulkImporter;
        $importer->import($rows);

        $this->assertSame(1, DeviceLocation::query()->count());
        $loc = DeviceLocation::query()->first();
        $this->assertSame('863540060109445', $loc->device_id);
        $this->assertEqualsWithDelta(51.2, (float) $loc->latitude, 0.0001);
        $this->assertEqualsWithDelta(20.0, (float) $loc->speed, 0.01);
    }
}
