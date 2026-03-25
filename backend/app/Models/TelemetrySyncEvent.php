<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetrySyncEvent extends Model
{
    public const SOURCE_SCHEDULER = 'scheduler';

    public const SOURCE_ADMIN_QUEUE = 'admin_queue';

    public const ACTION_INCREMENTAL_PULL = 'incremental_pull';

    public const ACTION_INCREMENTAL_SKIPPED = 'incremental_skipped';

    public const ACTION_BUILD_STOP_SESSIONS = 'build_stop_sessions';

    public const ACTION_AGGREGATE_DAILY = 'aggregate_daily';

    public const ACTION_HEATMAP_ROLLUP = 'heatmap_rollup';

    public const ACTION_MANUAL_VEHICLE_SYNC = 'manual_vehicle_sync';

    public const ACTION_MANUAL_SCOPE_SYNC = 'manual_scope_sync';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_INFO = 'info';

    protected $fillable = [
        'occurred_at',
        'source',
        'action',
        'status',
        'summary',
        'error_message',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
