<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyZoneImpression extends Model
{
    protected $fillable = [
        'stat_date',
        'zone_id',
        'campaign_id',
        'impressions',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'impressions' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GeoZone::class, 'zone_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
