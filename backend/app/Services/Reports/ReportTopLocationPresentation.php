<?php

namespace App\Services\Reports;

/**
 * PDF/table presentation for analytics_snapshot.top_locations rows.
 */
final class ReportTopLocationPresentation
{
    /**
     * Primary cell: resolved label or formatted coordinates.
     */
    public static function locationCell(array $loc): string
    {
        $label = $loc['label'] ?? null;
        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        $lat = (float) ($loc['lat'] ?? 0);
        $lng = (float) ($loc['lng'] ?? 0);

        return sprintf('%s°, %s°', number_format($lat, 2, '.', ''), number_format($lng, 2, '.', ''));
    }
}
