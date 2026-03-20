<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CampaignVehicle extends Pivot
{
    protected $table = 'campaign_vehicles';

    public $incrementing = true;

    protected $fillable = [
        'campaign_id',
        'vehicle_id',
        'placement_size_class',
        'agreed_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'agreed_price' => 'decimal:2',
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
