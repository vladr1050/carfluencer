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
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function stopSessions(): BelongsToMany
    {
        return $this->belongsToMany(StopSession::class, 'stop_session_zone', 'zone_id', 'stop_session_id')
            ->withTimestamps();
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
}
