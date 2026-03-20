<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertiserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'phone',
        'registration_number',
        'address',
        'discount_percent',
        'agency_commission_percent',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'agency_commission_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
