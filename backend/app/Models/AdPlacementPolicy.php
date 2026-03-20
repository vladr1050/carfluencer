<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacementPolicy extends Model
{
    protected $fillable = [
        'size_class',
        'base_price',
        'currency',
        'active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public static function basePriceForSize(string $sizeClass): ?string
    {
        $policy = static::query()
            ->where('size_class', $sizeClass)
            ->where('active', true)
            ->first();

        return $policy?->base_price;
    }
}
