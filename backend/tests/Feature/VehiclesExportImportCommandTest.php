<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VehiclesExportImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_then_import_roundtrip_by_imei(): void
    {
        $mo = User::factory()->mediaOwner()->create([
            'email' => 'fleet-owner@example.com',
        ]);

        Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'TestBrand',
            'model' => 'TestModel',
            'year' => 2023,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '111122223333444',
            'status' => 'active',
            'telemetry_pull_enabled' => true,
        ]);

        $relPath = 'storage/app/test-vehicles-export.json';
        $fullPath = storage_path('app/test-vehicles-export.json');

        $this->artisan('vehicles:export', ['--path' => $relPath])->assertExitCode(0);
        $this->assertFileExists($fullPath);

        Vehicle::query()->delete();
        $this->assertSame(0, Vehicle::query()->count());

        $this->artisan('vehicles:import', ['file' => $relPath])->assertExitCode(0);

        $this->assertSame(1, Vehicle::query()->count());
        $v = Vehicle::query()->first();
        $this->assertSame('111122223333444', $v->imei);
        $this->assertSame('TestBrand', $v->brand);
        $this->assertSame($mo->id, $v->media_owner_id);

        File::delete($fullPath);
    }
}
