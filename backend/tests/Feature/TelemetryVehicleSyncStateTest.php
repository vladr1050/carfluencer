<?php

namespace Tests\Feature;

use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use App\Models\DeviceLocation;
use App\Models\TelemetrySyncCursor;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelemetryVehicleSyncStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_job_records_success_on_vehicle(): void
    {
        $mo = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '555555555555555',
            'status' => 'active',
            'telemetry_pull_enabled' => true,
        ]);

        $mock = Mockery::mock(ClickHouseLocationCollector::class);
        $mock->shouldReceive('isEnabled')->once()->andReturn(true);
        $mock->shouldReceive('syncIncrementalForImei')->once()->with('555555555555555')->andReturn(12);
        $this->app->instance(ClickHouseLocationCollector::class, $mock);

        $job = new SyncVehicleTelemetryFromClickHouseJob($vehicle->id, 'incremental', null, null);
        $job->handle(
            $this->app->make(ClickHouseLocationCollector::class),
            $this->app->make(\App\Services\Telemetry\TelemetryVehicleSyncState::class),
        );

        $vehicle->refresh();
        $this->assertNotNull($vehicle->telemetry_last_incremental_at);
        $this->assertNotNull($vehicle->telemetry_last_success_at);
        $this->assertNull($vehicle->telemetry_last_error);
    }

    public function test_incremental_job_records_failure_and_rethrows(): void
    {
        $mo = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '666666666666666',
            'status' => 'active',
        ]);

        $mock = Mockery::mock(ClickHouseLocationCollector::class);
        $mock->shouldReceive('isEnabled')->once()->andReturn(true);
        $mock->shouldReceive('syncIncrementalForImei')->once()->andThrow(new \RuntimeException('CH down'));
        $this->app->instance(ClickHouseLocationCollector::class, $mock);

        $job = new SyncVehicleTelemetryFromClickHouseJob($vehicle->id, 'incremental', null, null);

        $this->expectException(\RuntimeException::class);

        try {
            $job->handle(
                $this->app->make(ClickHouseLocationCollector::class),
                $this->app->make(\App\Services\Telemetry\TelemetryVehicleSyncState::class),
            );
        } finally {
            $vehicle->refresh();
            $this->assertStringContainsString('CH down', (string) $vehicle->telemetry_last_error);
        }
    }

    public function test_historical_job_advances_incremental_cursor_using_device_locations(): void
    {
        $mo = User::factory()->mediaOwner()->create();
        $imei = '777777777777777';
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => $imei,
            'status' => 'active',
        ]);

        $mock = Mockery::mock(ClickHouseLocationCollector::class);
        $mock->shouldReceive('isEnabled')->once()->andReturn(true);
        $mock->shouldReceive('syncHistoricalForImei')
            ->once()
            ->andReturnUsing(function () use ($imei): int {
                DeviceLocation::query()->create([
                    'device_id' => $imei,
                    'event_at' => '2026-02-15 10:00:00',
                    'latitude' => 54.0,
                    'longitude' => 25.0,
                    'speed' => 10,
                    'battery' => null,
                    'gsm_signal' => null,
                    'odometer' => null,
                    'ignition' => true,
                    'extra_json' => null,
                ]);

                return 1;
            });
        $mock->shouldReceive('advanceIncrementalCursorAfterHistorical')
            ->once()
            ->withArgs(function (string $i, Carbon $upper) use ($imei): bool {
                return $i === $imei && $upper->year === 2026;
            });
        $this->app->instance(ClickHouseLocationCollector::class, $mock);

        $job = new SyncVehicleTelemetryFromClickHouseJob(
            $vehicle->id,
            'historical',
            '2026-02-01',
            '2026-02-28',
        );
        $job->handle(
            $this->app->make(ClickHouseLocationCollector::class),
            $this->app->make(\App\Services\Telemetry\TelemetryVehicleSyncState::class),
        );

        $vehicle->refresh();
        $this->assertNotNull($vehicle->telemetry_last_historical_at);
    }

    public function test_resolver_all_vehicles_respects_pull_enabled_flag(): void
    {
        $mo = User::factory()->mediaOwner()->create();
        Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'A',
            'model' => '1',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '888888888888888',
            'status' => 'active',
            'telemetry_pull_enabled' => true,
        ]);
        Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'B',
            'model' => '2',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '999999999999999',
            'status' => 'active',
            'telemetry_pull_enabled' => false,
        ]);

        $resolver = new TelemetrySyncImeiResolver;

        $this->assertCount(2, $resolver->resolve('all_vehicles'));
        $this->assertCount(1, $resolver->resolve('all_vehicles', null, [], true));
        $this->assertSame(['888888888888888'], $resolver->resolve('all_vehicles', null, [], true));
    }

    public function test_collector_advances_cursor_from_max_device_location(): void
    {
        $imei = '101010101010101';
        DeviceLocation::query()->create([
            'device_id' => $imei,
            'event_at' => '2026-01-05 08:30:00',
            'latitude' => 54.0,
            'longitude' => 25.0,
            'speed' => 5,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        $collector = $this->app->make(ClickHouseLocationCollector::class);
        $upper = Carbon::parse('2026-01-31', 'UTC')->endOfDay()->addSecond();
        $collector->advanceIncrementalCursorAfterHistorical($imei, $upper);

        $cursor = TelemetrySyncCursor::query()->where('cursor_key', 'clickhouse:imei:'.$imei.':incremental')->first();
        $this->assertNotNull($cursor);
        $this->assertSame('2026-01-05 08:30:00', $cursor->last_event_at->format('Y-m-d H:i:s'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
