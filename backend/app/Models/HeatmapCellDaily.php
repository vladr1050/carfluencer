<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeatmapCellDaily extends Model
{
    protected $table = 'heatmap_cells_daily';

    protected $fillable = [
        'day',
        'mode',
        'zoom_tier',
        'lat_bucket',
        'lng_bucket',
        'device_id',
        'samples_count',
        'weight_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'zoom_tier' => 'integer',
            'lat_bucket' => 'float',
            'lng_bucket' => 'float',
            'samples_count' => 'integer',
            'weight_value' => 'float',
        ];
    }
}
