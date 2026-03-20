<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StopSession extends Model
{
    protected $fillable = [
        'device_id',
        'started_at',
        'ended_at',
        'center_latitude',
        'center_longitude',
        'point_count',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'point_count' => 'integer',
        ];
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(GeoZone::class, 'stop_session_zone', 'stop_session_id', 'zone_id')
            ->withTimestamps();
    }
}
