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

        $explicit = config('reports.chrome_no_sandbox');
        if ($explicit !== null && $explicit !== '') {
            $noSandbox = filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        } else {
            $noSandbox = file_exists('/.dockerenv')
                || (is_string($chromePath) && str_contains($chromePath, '/snap/'));
        }

        if ($noSandbox) {
            $b->noSandbox();
        }
    }
}
