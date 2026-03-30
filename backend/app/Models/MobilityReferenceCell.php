<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobilityReferenceCell extends Model
{
    protected $fillable = [
        'cell_id',
        'vehicle_aadt',
        'pedestrian_daily',
        'average_speed_kmh',
        'hourly_peak_factor',
        'data_version',
        'records_count',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_aadt' => 'integer',
            'pedestrian_daily' => 'integer',
            'average_speed_kmh' => 'decimal:2',
            'hourly_peak_factor' => 'decimal:4',
            'records_count' => 'integer',
        ];
    }
}
