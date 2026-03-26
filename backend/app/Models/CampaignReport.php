<?php

namespace App\Models;

use App\Enums\CampaignReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignReport extends Model
{
    protected $fillable = [
        'campaign_id',
        'advertiser_id',
        'title',
        'report_type',
        'date_from',
        'date_to',
        'status',
        'include_driving_heatmap',
        'include_parking_heatmap',
        'file_path',
        'file_name',
        'file_size',
        'generated_at',
        'created_by',
        'error_message',
        'report_data_json',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'status' => CampaignReportStatus::class,
            'include_driving_heatmap' => 'boolean',
            'include_parking_heatmap' => 'boolean',
            'generated_at' => 'datetime',
            'report_data_json' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advertiser_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function storageDirectoryRelative(): string
    {
        return 'reports/'.$this->id;
    }
}
