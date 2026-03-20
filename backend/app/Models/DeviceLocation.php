<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceLocation extends Model
{
    protected $fillable = [
        'device_id',
        'event_at',
        'latitude',
        'longitude',
        'speed',
        'battery',
        'gsm_signal',
        'odometer',
        'ignition',
        'extra_json',
    ];

    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'speed' => 'float',
            'odometer' => 'float',
            'ignition' => 'boolean',
            'extra_json' => 'array',
        ];
    }
}
