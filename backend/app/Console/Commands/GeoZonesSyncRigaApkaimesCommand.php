<?php

namespace App\Console\Commands;

use App\Services\GeoZones\RigaApkaimesGeoZoneSync;
use Illuminate\Console\Command;

class GeoZonesSyncRigaApkaimesCommand extends Command
{
    protected $signature = 'geo-zones:sync-riga-apkaimes
                            {--dry-run : Show how many rows would be created/updated without saving}';

    protected $description = 'Create or update 58 GeoZones for Riga neighbourhoods (apkaimes); codes RIGA-APKAIME-01 … RIGA-APKAIME-58';

    public function handle(RigaApkaimesGeoZoneSync $sync): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Dry run: no database writes.');
        }

        $result = $sync->sync($dry);

        $this->info(sprintf(
            'Processed: %d (created: %d, updated: %d, skipped: %d)',
            $result['processed'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
