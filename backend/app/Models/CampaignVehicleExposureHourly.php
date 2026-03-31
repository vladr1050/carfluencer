<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignVehicleExposureHourly extends Model
{
    protected $table = 'campaign_vehicle_exposure_hourly';

    protected $fillable = [
        'campaign_id',
        'vehicle_id',
        'date',
        'hour',
        'cell_id',
        'mode',
        'exposure_seconds',
        'avg_vehicle_speed',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hour' => 'integer',
            'exposure_seconds' => 'integer',
            'avg_vehicle_speed' => 'decimal:2',
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
