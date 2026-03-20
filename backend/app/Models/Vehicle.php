<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehicle extends Model
{
    protected $fillable = [
        'media_owner_id',
        'brand',
        'model',
        'year',
        'color',
        'quantity',
        'image_path',
        'imei',
        'status',
        'notes',
        'telemetry_pull_enabled',
        'telemetry_last_incremental_at',
        'telemetry_last_historical_at',
        'telemetry_last_success_at',
        'telemetry_last_error',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quantity' => 'integer',
            'telemetry_pull_enabled' => 'boolean',
            'telemetry_last_incremental_at' => 'datetime',
            'telemetry_last_historical_at' => 'datetime',
            'telemetry_last_success_at' => 'datetime',
        ];
    }

    public function mediaOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'media_owner_id');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_vehicles')
            ->using(CampaignVehicle::class)
            ->withPivot(['placement_size_class', 'agreed_price', 'status'])
            ->withTimestamps();
    }
}
