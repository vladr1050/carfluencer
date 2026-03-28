<?php

namespace Tests\Unit;

use App\Services\Analytics\TopLocationLabelNormalizer;
use Tests\TestCase;

class TopLocationLabelNormalizerTest extends TestCase
{
    public function test_city_and_road_area(): void
    {
        $label = TopLocationLabelNormalizer::normalize([
            'address' => [
                'city' => 'Riga',
                'road' => 'Brīvības iela',
            ],
        ], 'nominatim');

        $this->assertSame('Riga / Brīvības iela area', $label);
    }

    public function test_city_central_when_no_sub_features(): void
    {
        $label = TopLocationLabelNormalizer::normalize([
            'address' => ['city' => 'Jūrmala'],
        ], 'nominatim');

        $this->assertSame('Jūrmala central area', $label);
    }

    public function test_unknown_provider_returns_null(): void
    {
        $this->assertNull(TopLocationLabelNormalizer::normalize(['address' => []], 'google'));
    }
}
