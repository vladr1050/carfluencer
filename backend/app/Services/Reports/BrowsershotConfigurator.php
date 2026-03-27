<?php

namespace App\Services\Reports;

use RuntimeException;
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
            if (str_contains($chromePath, '/snap/') && ! config('reports.allow_snap_chrome')) {
                throw new RuntimeException(
                    'Указан snap Chromium ('.$chromePath.'): под PHP/Supervisor он обычно не запускается (snap cgroup, каталог /var/www/snap). '
                    .'Установи Google Chrome: sudo bash deploy/install-google-chrome-for-browsershot.sh и в .env: CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/google-chrome-stable'
                );
            }
            $b->setChromePath($chromePath);
        }

        $profileDir = storage_path('app/browser-chromium-profile');
        if (! is_dir($profileDir)) {
            @mkdir($profileDir, 0775, true);
        }
        if (is_dir($profileDir) && is_writable($profileDir)) {
            $b->userDataDir($profileDir);
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
