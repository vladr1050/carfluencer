<?php

namespace Tests\Unit;

use App\Models\GeoZone;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GeoZoneModelTest extends TestCase
{
    public function test_validate_bounding_box_passes_for_valid_rectangle(): void
    {
        GeoZone::validateBoundingBox([
            'min_lat' => 56.9,
            'max_lat' => 57.0,
            'min_lng' => 24.0,
            'max_lng' => 24.2,
        ]);
        $this->assertTrue(true);
    }

    public function test_validate_bounding_box_rejects_inverted_lat(): void
    {
        $this->expectException(ValidationException::class);
        GeoZone::validateBoundingBox([
            'min_lat' => 57.0,
            'max_lat' => 56.9,
            'min_lng' => 24.0,
            'max_lng' => 24.2,
        ]);
    }
}
