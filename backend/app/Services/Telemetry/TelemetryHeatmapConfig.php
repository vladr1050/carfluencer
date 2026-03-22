<?php

namespace App\Services\Telemetry;

use App\Models\PlatformSetting;

/**
 * Admin-editable heatmap options (Filament → platform_settings). Env/config is fallback when unset.
 */
final class TelemetryHeatmapConfig
{
    public const KEY_INTENSITY_GAMMA = 'telemetry_heatmap_intensity_gamma';

    public static function intensityGamma(): float
    {
        $row = PlatformSetting::get(self::KEY_INTENSITY_GAMMA);
        if ($row !== null && $row !== '') {
            $g = (float) $row;

            return max(1.0, min(3.0, $g));
        }

        $g = (float) config('telemetry.heatmap.intensity_gamma', 1.55);

        return max(1.0, min(3.0, $g));
    }

    /**
     * @return array{intensity_gamma: string}
     */
    public static function allForForm(): array
    {
        $g = self::intensityGamma();
        $s = number_format($g, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.') ?: '1';

        return [
            'intensity_gamma' => $s,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveFromForm(array $data): void
    {
        $g = (float) ($data['intensity_gamma'] ?? 1.55);
        $g = max(1.0, min(3.0, $g));
        PlatformSetting::set(self::KEY_INTENSITY_GAMMA, (string) $g);
    }
}
