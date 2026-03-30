<?php

namespace Tests\Unit;

use App\Support\Geo\RigaPriekspilsetas;
use Tests\TestCase;

class RigaPriekspilsetasTest extends TestCase
{
    public function test_feature_collection_has_six_riga_districts(): void
    {
        $fc = RigaPriekspilsetas::featureCollection();

        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertCount(6, $fc['features']);
        foreach ($fc['features'] as $f) {
            $this->assertSame('Feature', $f['type']);
            $this->assertSame('Polygon', $f['geometry']['type']);
            $this->assertNotEmpty($f['geometry']['coordinates'][0]);
            $this->assertArrayHasKey('name_lv', $f['properties']);
        }
    }
}
