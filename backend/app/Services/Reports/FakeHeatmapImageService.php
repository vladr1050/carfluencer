<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\HeatmapImageServiceInterface;

/**
 * Placeholder PNG for tests / environments without Puppeteer.
 */
final class FakeHeatmapImageService implements HeatmapImageServiceInterface
{
    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath,
        string $viewportId = 'full',
    ): void {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        file_put_contents($absolutePath, $png !== false ? $png : '');
    }
}
