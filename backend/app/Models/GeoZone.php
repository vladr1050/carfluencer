<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
