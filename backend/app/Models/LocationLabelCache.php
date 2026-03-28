<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationLabelCache extends Model
{
    protected $table = 'location_label_cache';

    protected $fillable = [
        'lat_bucket',
        'lng_bucket',
        'provider',
        'label',
        'raw_response_json',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat_bucket' => 'float',
            'lng_bucket' => 'float',
            'raw_response_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
