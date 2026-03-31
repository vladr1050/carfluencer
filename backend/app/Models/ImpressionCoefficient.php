<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpressionCoefficient extends Model
{
    protected $fillable = [
        'version',
        'vehicle_visibility_share',
        'pedestrian_visibility_share',
        'pedestrian_parking_share',
        'roadside_vehicle_share',
        'speed_factor_low',
        'speed_factor_medium',
        'speed_factor_high',
        'speed_factor_very_high',
        'dwell_factor_short',
        'dwell_factor_medium',
        'dwell_factor_long',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_visibility_share' => 'decimal:6',
            'pedestrian_visibility_share' => 'decimal:6',
            'pedestrian_parking_share' => 'decimal:6',
            'roadside_vehicle_share' => 'decimal:6',
            'speed_factor_low' => 'decimal:4',
            'speed_factor_medium' => 'decimal:4',
            'speed_factor_high' => 'decimal:4',
            'speed_factor_very_high' => 'decimal:4',
            'dwell_factor_short' => 'decimal:4',
            'dwell_factor_medium' => 'decimal:4',
            'dwell_factor_long' => 'decimal:4',
        ];
    }
}
