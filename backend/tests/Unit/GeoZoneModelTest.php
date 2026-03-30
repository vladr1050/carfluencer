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

    public function test_contains_point_uses_bounding_box_when_polygon_absent(): void
    {
        $z = new GeoZone([
            'min_lat' => 56.0,
            'max_lat' => 57.0,
            'min_lng' => 24.0,
            'max_lng' => 25.0,
            'polygon_geojson' => null,
        ]);

        $this->assertTrue($z->containsPoint(56.5, 24.5));
        $this->assertFalse($z->containsPoint(55.5, 24.5));
    }

    public function test_contains_point_uses_polygon_when_set(): void
    {
        // Bbox is the full unit square; polygon is a small inner square — points inside bbox but outside polygon must miss.
        $z = new GeoZone([
            'min_lat' => 0.0,
            'max_lat' => 1.0,
            'min_lng' => 0.0,
            'max_lng' => 1.0,
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [[0.4, 0.4], [0.6, 0.4], [0.6, 0.6], [0.4, 0.6], [0.4, 0.4]],
                ],
            ],
        ]);

        $this->assertTrue($z->containsPoint(0.5, 0.5));
        $this->assertFalse($z->containsPoint(0.1, 0.1));
    }

    public function test_normalize_geometry_fields_from_polygon_sets_envelope(): void
    {
        $data = GeoZone::normalizeGeometryFields([
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [[24.0, 56.9], [24.2, 56.9], [24.15, 57.0], [24.0, 56.9]],
                ],
            ],
            'min_lat' => 0.0,
            'max_lat' => 1.0,
            'min_lng' => 0.0,
            'max_lng' => 1.0,
        ]);

        $this->assertSame(56.9, $data['min_lat']);
        $this->assertSame(57.0, $data['max_lat']);
        $this->assertSame(24.0, $data['min_lng']);
        $this->assertSame(24.2, $data['max_lng']);
        $this->assertIsArray($data['polygon_geojson']);
        $this->assertSame('Polygon', $data['polygon_geojson']['type']);
    }

    public function test_normalize_geometry_fields_clears_empty_polygon(): void
    {
        $data = GeoZone::normalizeGeometryFields([
            'polygon_geojson' => null,
            'min_lat' => 56.9,
            'max_lat' => 57.0,
            'min_lng' => 24.0,
            'max_lng' => 24.2,
        ]);

        $this->assertNull($data['polygon_geojson']);
        $this->assertSame(56.9, $data['min_lat']);
    }

    public function test_contains_point_multi_polygon_matches_any_part(): void
    {
        $z = new GeoZone([
            'min_lat' => 0.0,
            'max_lat' => 1.0,
            'min_lng' => 0.0,
            'max_lng' => 1.0,
            'polygon_geojson' => [
                'type' => 'MultiPolygon',
                'coordinates' => [
                    [[[0.1, 0.1], [0.2, 0.1], [0.2, 0.2], [0.1, 0.2], [0.1, 0.1]]],
                    [[[0.7, 0.7], [0.8, 0.7], [0.8, 0.8], [0.7, 0.8], [0.7, 0.7]]],
                ],
            ],
        ]);

        $this->assertTrue($z->containsPoint(0.15, 0.15));
        $this->assertTrue($z->containsPoint(0.75, 0.75));
        $this->assertFalse($z->containsPoint(0.5, 0.5));
    }

    public function test_normalize_geometry_fields_from_multi_polygon_sets_envelope(): void
    {
        $data = GeoZone::normalizeGeometryFields([
            'polygon_geojson' => [
                'type' => 'MultiPolygon',
                'coordinates' => [
                    [[[24.0, 56.9], [24.1, 56.9], [24.05, 57.0], [24.0, 56.9]]],
                    [[[24.2, 56.95], [24.3, 56.95], [24.25, 57.02], [24.2, 56.95]]],
                ],
            ],
            'min_lat' => 0.0,
            'max_lat' => 1.0,
            'min_lng' => 0.0,
            'max_lng' => 1.0,
        ]);

        $this->assertSame('MultiPolygon', $data['polygon_geojson']['type']);
        $this->assertCount(2, $data['polygon_geojson']['coordinates']);
        $this->assertSame(56.9, $data['min_lat']);
        $this->assertSame(57.02, $data['max_lat']);
        $this->assertSame(24.0, $data['min_lng']);
        $this->assertSame(24.3, $data['max_lng']);
    }
}
