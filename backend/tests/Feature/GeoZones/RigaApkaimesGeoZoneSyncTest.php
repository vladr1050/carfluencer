<?php

namespace Tests\Feature\GeoZones;

use App\Models\GeoZone;
use App\Services\GeoZones\RigaApkaimesGeoZoneSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RigaApkaimesGeoZoneSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_58_zones_with_stable_codes_and_names(): void
    {
        $sync = app(RigaApkaimesGeoZoneSync::class);
        $result = $sync->sync(false);

        $this->assertSame(58, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $this->assertSame(58, GeoZone::query()->where('code', 'like', 'RIGA-APKAIME-%')->count());

        $beberbeki = GeoZone::query()->where('code', 'RIGA-APKAIME-01')->first();
        $this->assertNotNull($beberbeki);
        $this->assertSame('Beberbeķi', $beberbeki->name);
        $this->assertTrue($beberbeki->active);
        $this->assertSame('Polygon', $beberbeki->polygon_geojson['type'] ?? null);
    }

    public function test_second_sync_updates_instead_of_duplicating(): void
    {
        $sync = app(RigaApkaimesGeoZoneSync::class);
        $sync->sync(false);
        $result = $sync->sync(false);

        $this->assertSame(0, $result['created']);
        $this->assertSame(58, $result['updated']);
        $this->assertSame(58, GeoZone::query()->count());
    }

    public function test_code_for_apkaime_id_is_zero_padded(): void
    {
        $this->assertSame('RIGA-APKAIME-01', RigaApkaimesGeoZoneSync::codeForApkaimeId(1));
        $this->assertSame('RIGA-APKAIME-58', RigaApkaimesGeoZoneSync::codeForApkaimeId(58));
    }
}
