<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'vehicle_id',
        'trip_status',
        'trip_end',
    ];

    protected function casts(): array
    {
        return [
            'trip_end' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
