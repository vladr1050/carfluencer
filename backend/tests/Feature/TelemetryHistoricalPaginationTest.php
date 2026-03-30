<?php

namespace Tests\Feature;

use App\Models\DeviceLocation;
use App\Services\Telemetry\ClickHouseHttpClient;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\DeviceLocationBulkImporter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelemetryHistoricalPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_sync_pages_until_batch_smaller_than_limit(): void
    {
        config(['telemetry.clickhouse.enabled' => true]);
        config(['telemetry.clickhouse.schema_preset' => 'location']);
        config(['telemetry.clickhouse.timestamp_type' => 'unix_seconds']);
        config(['telemetry.clickhouse.historical_rows_per_chunk' => 2]);
        config(['telemetry.clickhouse.historical_max_pages' => 100]);

        $rowsPage1 = [
            ['device_id' => '111111111111111', 'timestamp' => 100, 'latitude' => 1.0, 'longitude' => 2.0, 'speed' => 0, 'io' => null],
            ['device_id' => '111111111111111', 'timestamp' => 101, 'latitude' => 1.1, 'longitude' => 2.0, 'speed' => 0, 'io' => null],
        ];
        $rowsPage2 = [
            ['device_id' => '111111111111111', 'timestamp' => 102, 'latitude' => 1.2, 'longitude' => 2.0, 'speed' => 0, 'io' => null],
        ];

        $calls = 0;
        $client = Mockery::mock(ClickHouseHttpClient::class);
        $client->shouldReceive('consumeJsonEachRowBatches')->andReturnUsing(function (string $sql, int $batchSize, callable $onBatch) use (&$calls, $rowsPage1, $rowsPage2): void {
            $calls++;
            $rows = $calls === 1 ? $rowsPage1 : $rowsPage2;
            $onBatch($rows);
        });

        $importer = $this->app->make(DeviceLocationBulkImporter::class);
        $collector = new ClickHouseLocationCollector($client, $importer);

        $imported = $collector->syncHistoricalForImei(
            '111111111111111',
            Carbon::parse('1970-01-01 UTC'),
            Carbon::parse('2100-01-01 UTC'),
            2
        );

        $this->assertSame(2, $calls);
        $this->assertSame(3, $imported);
        $this->assertSame(3, DeviceLocation::query()->count());
    }
}
