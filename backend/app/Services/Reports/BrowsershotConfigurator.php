<?php

namespace App\Services\Reports;

use Spatie\Browsershot\Browsershot;

/**
 * Shared Chrome/Node options for campaign report screenshots and PDFs.
 */
final class BrowsershotConfigurator
{
    public static function apply(Browsershot $b): void
    {
        $chromePath = config('reports.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $b->setChromePath($chromePath);
        }

        $nodeBinary = config('reports.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $b->setNodeBinary($nodeBinary);
        }

        $npmBinary = config('reports.npm_binary');
        if (is_string($npmBinary) && $npmBinary !== '') {
            $b->setNpmBinary($npmBinary);
        }

        $noSandbox = filter_var(config('reports.chrome_no_sandbox'), FILTER_VALIDATE_BOOLEAN)
            || file_exists('/.dockerenv');

        if ($noSandbox) {
            $b->noSandbox();
        }
    }
}
