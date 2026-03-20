<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetrySyncCursor extends Model
{
    protected $fillable = [
        'cursor_key',
        'last_event_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
