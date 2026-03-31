<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignImpressionStat extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'date_from',
        'date_to',
        'vehicles_count',
        'driving_impressions',
        'parking_impressions',
        'total_gross_impressions',
        'campaign_price',
        'cpm',
        'calculation_version',
        'mobility_data_version',
        'coefficients_version',
        'telemetry_sampling_seconds',
        'input_fingerprint',
        'matched_direct_count',
        'matched_fallback_count',
        'unmatched_count',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'vehicles_count' => 'integer',
            'driving_impressions' => 'integer',
            'parking_impressions' => 'integer',
            'total_gross_impressions' => 'integer',
            'campaign_price' => 'decimal:2',
            'cpm' => 'decimal:4',
            'telemetry_sampling_seconds' => 'integer',
            'matched_direct_count' => 'integer',
            'matched_fallback_count' => 'integer',
            'unmatched_count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
