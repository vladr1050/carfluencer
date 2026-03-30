<?php

namespace App\Services\Reports;

/**
 * PDF heatmap frames: same data, different map extents (config: reports.heatmap_export.viewports).
 *
 * @phpstan-type ViewportDef array{
 *     id: string,
 *     label: string,
 *     fit_to_data: bool,
 *     south?: float,
 *     west?: float,
 *     north?: float,
 *     east?: float
 * }
 */
final class ReportHeatmapViewports
{
    /**
     * @return list<ViewportDef>
     */
    public static function all(): array
    {
        $raw = config('reports.heatmap_export.viewports');
        if (! is_array($raw) || $raw === []) {
            return [self::fallbackFull()];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row) || empty($row['id']) || ! is_string($row['id'])) {
                continue;
            }
            $id = $row['id'];
            $label = isset($row['label']) && is_string($row['label']) ? $row['label'] : $id;
            $fit = filter_var($row['fit_to_data'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($fit) {
                $out[] = ['id' => $id, 'label' => $label, 'fit_to_data' => true];

                continue;
            }
            $s = isset($row['south']) ? (float) $row['south'] : null;
            $w = isset($row['west']) ? (float) $row['west'] : null;
            $n = isset($row['north']) ? (float) $row['north'] : null;
            $e = isset($row['east']) ? (float) $row['east'] : null;
            if ($s === null || $w === null || $n === null || $e === null || $s >= $n || $w >= $e) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => $label,
                'fit_to_data' => false,
                'south' => $s,
                'west' => $w,
                'north' => $n,
                'east' => $e,
            ];
        }

        return $out !== [] ? $out : [self::fallbackFull()];
    }

    public static function label(string $id): string
    {
        foreach (self::all() as $v) {
            if ($v['id'] === $id) {
                return $v['label'];
            }
        }

        return $id;
    }

    /**
     * @return ViewportDef|null
     */
    public static function byId(string $id): ?array
    {
        foreach (self::all() as $v) {
            if ($v['id'] === $id) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @return ViewportDef
     */
    private static function fallbackFull(): array
    {
        $b = config('reports.heatmap_export.bounds', []);
        $s = isset($b['south']) ? (float) $b['south'] : 53.70;
        $w = isset($b['west']) ? (float) $b['west'] : 20.70;
        $n = isset($b['north']) ? (float) $b['north'] : 59.75;
        $e = isset($b['east']) ? (float) $b['east'] : 28.52;

        return [
            'id' => 'baltics',
            'label' => 'Baltics (Estonia, Latvia, Lithuania)',
            'fit_to_data' => false,
            'south' => $s,
            'west' => $w,
            'north' => $n,
            'east' => $e,
        ];
    }
}
