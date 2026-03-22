<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehicle extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_IN_CAMPAIGN = 'in_campaign';

    public const STATUS_NOT_AVAILABLE = 'not_available';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_BOOKED,
        self::STATUS_IN_CAMPAIGN,
        self::STATUS_NOT_AVAILABLE,
    ];

    protected $fillable = [
        'media_owner_id',
        'brand',
        'model',
        'year',
        'color_key',
        'quantity',
        'image_path',
        'imei',
        'status',
        'notes',
        'telemetry_pull_enabled',
        'telemetry_last_incremental_at',
        'telemetry_last_historical_at',
        'telemetry_last_success_at',
        'telemetry_last_error',
    ];

    protected $appends = [
        'color_label',
        'status_label',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quantity' => 'integer',
            'telemetry_pull_enabled' => 'boolean',
            'telemetry_last_incremental_at' => 'datetime',
            'telemetry_last_historical_at' => 'datetime',
            'telemetry_last_success_at' => 'datetime',
        ];
    }

    /**
     * Statuses shown in advertiser "catalog" (available to attach to a campaign).
     *
     * @return list<string>
     */
    public static function catalogVisibleStatuses(): array
    {
        return [self::STATUS_ACTIVE];
    }

    public function getColorLabelAttribute(): ?string
    {
        $key = $this->color_key;
        if ($key === null || $key === '') {
            return null;
        }

        $colors = config('vehicle.colors', []);

        return $colors[$key] ?? $key;
    }

    public function getStatusLabelAttribute(): string
    {
        $statuses = config('vehicle.fleet_statuses', []);

        return (string) ($statuses[$this->status] ?? $this->status);
    }

    public function mediaOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'media_owner_id');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_vehicles')
            ->using(CampaignVehicle::class)
            ->withPivot(['placement_size_class', 'agreed_price', 'status'])
            ->withTimestamps();
    }
}
