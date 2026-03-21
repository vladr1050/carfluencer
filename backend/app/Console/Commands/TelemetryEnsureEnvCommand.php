<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Appends telemetry-related keys from deploy/telemetry.env.fragment into backend/.env
 * when those keys are missing (idempotent). Safe to run on every deploy.
 */
class TelemetryEnsureEnvCommand extends Command
{
    protected $signature = 'telemetry:ensure-env
                            {--dry-run : Print lines that would be appended, do not write}';

    protected $description = 'Merge missing TELEMETRY_* keys from deploy/telemetry.env.fragment into .env';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->comment('No backend/.env file; skip telemetry:ensure-env.');

            return self::SUCCESS;
        }

        $fragmentPath = dirname(base_path()).DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'telemetry.env.fragment';
        if (! is_file($fragmentPath)) {
            $this->warn("Fragment not found: {$fragmentPath}");

            return self::SUCCESS;
        }

        $envContent = (string) file_get_contents($envPath);
        $existingKeys = $this->parseExistingKeys($envContent);

        $toAppend = [];
        $lines = preg_split("/\r\n|\n|\r/", (string) file_get_contents($fragmentPath)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            $key = trim(Str::before($line, '='));
            if ($key === '') {
                continue;
            }
            if (array_key_exists($key, $existingKeys)) {
                continue;
            }
            $toAppend[] = $line;
            $existingKeys[$key] = true;
        }

        if ($toAppend === []) {
            $this->comment('Telemetry env: all fragment keys already present in .env.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Would append:');
            foreach ($toAppend as $l) {
                $this->line('  '.$l);
            }

            return self::SUCCESS;
        }

        $block = "\n# --- Telemetry (auto from deploy/telemetry.env.fragment) ---\n";
        $block .= implode("\n", $toAppend)."\n";
        file_put_contents($envPath, $envContent.$block, LOCK_EX);
        $this->info('Appended '.count($toAppend).' telemetry key(s) to .env.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function parseExistingKeys(string $envContent): array
    {
        $keys = [];
        foreach (preg_split("/\r\n|\n|\r/", $envContent) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            $key = trim(Str::before($line, '='));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return $keys;
    }
}
