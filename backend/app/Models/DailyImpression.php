<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyImpression extends Model
{
    protected $fillable = [
        'stat_date',
        'campaign_id',
        'vehicle_id',
        'impressions',
        'driving_distance_km',
        'parking_minutes',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'impressions' => 'integer',
            'driving_distance_km' => 'float',
            'parking_minutes' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
