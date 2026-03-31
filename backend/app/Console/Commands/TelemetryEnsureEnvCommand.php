<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Appends keys from deploy/*.env.fragment into backend/.env when those keys are missing
 * (idempotent). Safe to run on every deploy.
 */
class TelemetryEnsureEnvCommand extends Command
{
    protected $signature = 'telemetry:ensure-env
                            {--dry-run : Print lines that would be appended, do not write}';

    protected $description = 'Merge missing keys from deploy/*.env.fragment files into .env';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->comment('No backend/.env file; skip telemetry:ensure-env.');

            return self::SUCCESS;
        }

        $deployDir = dirname(base_path()).DIRECTORY_SEPARATOR.'deploy';
        $fragmentPaths = [
            $deployDir.DIRECTORY_SEPARATOR.'telemetry.env.fragment',
            $deployDir.DIRECTORY_SEPARATOR.'impression_engine.env.fragment',
        ];

        $envContent = (string) file_get_contents($envPath);
        $existingKeys = $this->parseExistingKeys($envContent);

        $sections = [];
        foreach ($fragmentPaths as $fragmentPath) {
            if (! is_file($fragmentPath)) {
                continue;
            }

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

            if ($toAppend !== []) {
                $sections[] = [
                    'file' => basename($fragmentPath),
                    'lines' => $toAppend,
                ];
            }
        }

        if ($sections === []) {
            $this->comment('Env fragments: all keys already present in .env.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Would append:');
            foreach ($sections as $section) {
                $this->line('  # from deploy/'.$section['file']);
                foreach ($section['lines'] as $l) {
                    $this->line('  '.$l);
                }
            }

            return self::SUCCESS;
        }

        $block = '';
        foreach ($sections as $section) {
            $block .= "\n# --- Auto from deploy/{$section['file']} ---\n";
            $block .= implode("\n", $section['lines'])."\n";
        }
        file_put_contents($envPath, $envContent.$block, LOCK_EX);
        $total = array_sum(array_map(fn (array $s): int => count($s['lines']), $sections));
        $this->info("Appended {$total} key(s) to .env from ".count($sections).' fragment file(s).');

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
