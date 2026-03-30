<?php

namespace Tests\Unit;

use App\Support\Geo\RigaApkaimes;
use Tests\TestCase;

class RigaApkaimesTest extends TestCase
{
    public function test_feature_collection_has_58_riga_neighbourhoods(): void
    {
        $fc = RigaApkaimes::featureCollection();

        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertCount(58, $fc['features']);
        foreach ($fc['features'] as $f) {
            $this->assertSame('Feature', $f['type']);
            $this->assertSame('Polygon', $f['geometry']['type']);
            $this->assertNotEmpty($f['geometry']['coordinates'][0]);
            $this->assertArrayHasKey('name_lv', $f['properties']);
        }
    }
}
