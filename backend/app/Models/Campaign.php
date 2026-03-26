<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    protected $fillable = [
        'advertiser_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by_admin',
        'created_by_user_id',
        'discount_percent',
        'platform_commission_percent',
        'agency_commission_percent',
        'total_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'created_by_admin' => 'boolean',
            'discount_percent' => 'decimal:2',
            'platform_commission_percent' => 'decimal:2',
            'agency_commission_percent' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advertiser_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function campaignVehicles(): HasMany
    {
        return $this->hasMany(CampaignVehicle::class);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'campaign_vehicles')
            ->using(CampaignVehicle::class)
            ->withPivot(['placement_size_class', 'agreed_price', 'status'])
            ->withTimestamps();
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(CampaignProof::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CampaignReport::class);
    }

    /**
     * Aggregated ClickHouse → PostgreSQL sync stats for vehicles on this campaign (admin UX).
     */
    public function telemetryLinkedVehiclesSummaryLine(): string
    {
        $q = Vehicle::query()->whereIn('id', function ($sub) {
            $sub->select('vehicle_id')
                ->from('campaign_vehicles')
                ->where('campaign_id', $this->id);
        });

        $total = (clone $q)->count();
        if ($total === 0) {
            return __('No linked vehicles.');
        }

        $pullOn = (clone $q)->where('telemetry_pull_enabled', true)->count();
        $withError = (clone $q)->whereNotNull('telemetry_last_error')->count();
        $lastRaw = (clone $q)->max('telemetry_last_success_at');
        $last = $lastRaw !== null
            ? Carbon::parse($lastRaw)->timezone(config('app.timezone'))->format('Y-m-d H:i')
            : '—';

        return __(':total vehicle(s): :on with scheduled pull; :err with last error; last success: :last', [
            'total' => $total,
            'on' => $pullOn,
            'err' => $withError,
            'last' => $last,
        ]);
    }
}
