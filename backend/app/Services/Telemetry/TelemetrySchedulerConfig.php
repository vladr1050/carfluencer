<?php

namespace App\Services\Telemetry;

use App\Models\PlatformSetting;

/**
 * Persisted telemetry scheduler settings (Filament + console tick).
 */
final class TelemetrySchedulerConfig
{
    public const KEY_INCREMENTAL_INTERVAL = 'telemetry_incremental_interval_minutes';

    public const KEY_BUILD_SESSIONS_AT = 'telemetry_build_sessions_at';

    public const KEY_AGGREGATE_DAILY_AT = 'telemetry_aggregate_daily_at';

    public const KEY_LAST_INCREMENTAL_RUN = 'telemetry_last_incremental_run_at';

    public static function incrementalIntervalMinutes(): int
    {
        $v = (int) (PlatformSetting::get(self::KEY_INCREMENTAL_INTERVAL) ?? 5);

        return max(1, min(1440, $v > 0 ? $v : 5));
    }

    public static function buildSessionsAt(): string
    {
        $v = PlatformSetting::get(self::KEY_BUILD_SESSIONS_AT) ?? '01:10';

        return self::normalizeTime($v);
    }

    public static function aggregateDailyAt(): string
    {
        $v = PlatformSetting::get(self::KEY_AGGREGATE_DAILY_AT) ?? '01:40';

        return self::normalizeTime($v);
    }

    public static function normalizeTime(string $value): string
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {
            return $value;
        }

        return '01:10';
    }

    /**
     * @return array<string, string|null>
     */
    public static function allForForm(): array
    {
        return [
            'incremental_interval_minutes' => (string) self::incrementalIntervalMinutes(),
            'build_sessions_at' => self::buildSessionsAt(),
            'aggregate_daily_at' => self::aggregateDailyAt(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveFromForm(array $data): void
    {
        PlatformSetting::set(
            self::KEY_INCREMENTAL_INTERVAL,
            (string) max(1, min(1440, (int) ($data['incremental_interval_minutes'] ?? 5)))
        );
        PlatformSetting::set(
            self::KEY_BUILD_SESSIONS_AT,
            self::normalizeTime((string) ($data['build_sessions_at'] ?? '01:10'))
        );
        PlatformSetting::set(
            self::KEY_AGGREGATE_DAILY_AT,
            self::normalizeTime((string) ($data['aggregate_daily_at'] ?? '01:40'))
        );
    }
}
