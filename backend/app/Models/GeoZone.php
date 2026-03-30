<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class GeoZone extends Model
{
    protected $fillable = [
        'code',
        'name',
        'min_lat',
        'max_lat',
        'min_lng',
        'max_lng',
        'polygon_geojson',
        'active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'min_lat' => 'float',
            'max_lat' => 'float',
            'min_lng' => 'float',
            'max_lng' => 'float',
            'polygon_geojson' => 'array',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function stopSessions(): BelongsToMany
    {
        return $this->belongsToMany(StopSession::class, 'stop_session_zone', 'zone_id', 'stop_session_id')
            ->withTimestamps();
    }

    public function containsPoint(float $lat, float $lng): bool
    {
        $ring = $this->polygonOuterRingLngLat();
        if ($ring !== null && count($ring) >= 4) {
            return self::pointInPolygonRing($lat, $lng, $ring);
        }

        return $lat >= $this->min_lat && $lat <= $this->max_lat
            && $lng >= $this->min_lng && $lng <= $this->max_lng;
    }

    /**
     * @return list<array{0: float, 1: float}>|null Outer ring as [lng, lat], closed (first equals last).
     */
    public function polygonOuterRingLngLat(): ?array
    {
        $poly = $this->polygon_geojson;
        if (! is_array($poly) || ($poly['type'] ?? '') !== 'Polygon') {
            return null;
        }
        $coords = $poly['coordinates'] ?? null;
        if (! is_array($coords) || ! isset($coords[0]) || ! is_array($coords[0])) {
            return null;
        }

        /** @var list<mixed> $ring */
        $ring = $coords[0];
        $out = [];
        foreach ($ring as $pt) {
            if (! is_array($pt) || count($pt) < 2) {
                continue;
            }
            $out[] = [(float) $pt[0], (float) $pt[1]];
        }

        return count($out) >= 4 ? $out : null;
    }

    /**
     * Planar ray casting on lng/lat (adequate for city-scale zones).
     *
     * @param  list<array{0: float, 1: float}>  $ring  [lng, lat], closed
     */
    public static function pointInPolygonRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];
            if (abs($yj - $yi) < 1.0e-12) {
                continue;
            }
            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeGeometryFields(array $data): array
    {
        $raw = $data['polygon_geojson'] ?? null;
        if ($raw === null || $raw === '' || $raw === [] || $raw === 'null') {
            $data['polygon_geojson'] = null;

            return $data;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'polygon_geojson' => 'Invalid polygon data.',
            ]);
        }

        if (($raw['type'] ?? '') !== 'Polygon') {
            throw ValidationException::withMessages([
                'polygon_geojson' => 'Only Polygon geometry is supported.',
            ]);
        }

        $coords = $raw['coordinates'] ?? null;
        if (! is_array($coords) || ! isset($coords[0]) || ! is_array($coords[0])) {
            throw ValidationException::withMessages([
                'polygon_geojson' => 'Polygon must include a coordinates array with an outer ring.',
            ]);
        }

        /** @var list<mixed> $outer */
        $outer = $coords[0];
        $ring = self::normalizePolygonOuterRingVertices($outer);

        $minLat = PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;
        $minLng = PHP_FLOAT_MAX;
        $maxLng = -PHP_FLOAT_MAX;
        foreach ($ring as $pt) {
            $lng = $pt[0];
            $lat = $pt[1];
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }

        $data['polygon_geojson'] = [
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ];
        $data['min_lat'] = $minLat;
        $data['max_lat'] = $maxLat;
        $data['min_lng'] = $minLng;
        $data['max_lng'] = $maxLng;

        return $data;
    }

    /**
     * @param  list<mixed>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function normalizePolygonOuterRingVertices(array $ring): array
    {
        $norm = [];
        foreach ($ring as $pt) {
            if (! is_array($pt) || count($pt) < 2) {
                continue;
            }
            $lng = (float) $pt[0];
            $lat = (float) $pt[1];
            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                throw ValidationException::withMessages([
                    'polygon_geojson' => 'Polygon vertices must use WGS84 latitude ∈ [-90,90] and longitude ∈ [-180,180].',
                ]);
            }
            $norm[] = [$lng, $lat];
        }

        if (count($norm) < 3) {
            throw ValidationException::withMessages([
                'polygon_geojson' => 'Polygon needs at least three distinct vertices.',
            ]);
        }

        $first = $norm[0];
        $last = $norm[count($norm) - 1];
        $eps = 1e-9;
        if (abs($first[0] - $last[0]) > $eps || abs($first[1] - $last[1]) > $eps) {
            $norm[] = [$first[0], $first[1]];
        }

        return $norm;
    }

    /**
     * @param  array{min_lat?: mixed, max_lat?: mixed, min_lng?: mixed, max_lng?: mixed}  $data
     */
    public static function validateBoundingBox(array $data): void
    {
        $minLat = (float) ($data['min_lat'] ?? 0);
        $maxLat = (float) ($data['max_lat'] ?? 0);
        $minLng = (float) ($data['min_lng'] ?? 0);
        $maxLng = (float) ($data['max_lng'] ?? 0);

        $errors = [];
        if ($minLat < -90.0 || $minLat > 90.0) {
            $errors['min_lat'] = 'South latitude must be between -90 and 90.';
        }
        if ($maxLat < -90.0 || $maxLat > 90.0) {
            $errors['max_lat'] = 'North latitude must be between -90 and 90.';
        }
        if ($minLng < -180.0 || $minLng > 180.0) {
            $errors['min_lng'] = 'West longitude must be between -180 and 180.';
        }
        if ($maxLng < -180.0 || $maxLng > 180.0) {
            $errors['max_lng'] = 'East longitude must be between -180 and 180.';
        }
        if ($minLat >= $maxLat) {
            $errors['min_lat'] = 'South (min) latitude must be less than north (max) latitude.';
        }
        if ($minLng >= $maxLng) {
            $errors['min_lng'] = 'West (min) longitude must be less than east (max) longitude.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data  After {@see normalizeGeometryFields()}.
     */
    public static function validateZoneGeometry(array $data): void
    {
        self::validateBoundingBox($data);
    }
}
