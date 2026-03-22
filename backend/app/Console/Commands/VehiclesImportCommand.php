<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VehiclesImportCommand extends Command
{
    protected $signature = 'vehicles:import
                            {file : Path to JSON from vehicles:export (relative to base_path or absolute)}
                            {--default-media-owner-email= : Use this user email when row has no media_owner_email}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Import vehicles from vehicles:export JSON; matches media_owner by user email (updateOrCreate by imei).';

    public function handle(): int
    {
        $rawPath = (string) $this->argument('file');
        $path = str_starts_with($rawPath, DIRECTORY_SEPARATOR) ? $rawPath : base_path($rawPath);

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded) || ! isset($decoded['vehicles']) || ! is_array($decoded['vehicles'])) {
            $this->error('Invalid JSON: expected { "vehicles": [ ... ] }');

            return self::FAILURE;
        }

        $defaultEmail = $this->option('default-media-owner-email') ?: null;
        $dry = (bool) $this->option('dry-run');

        $imported = 0;
        $skipped = 0;

        foreach ($decoded['vehicles'] as $index => $row) {
            if (! is_array($row)) {
                $this->warn("Skipping index {$index}: not an object.");
                $skipped++;

                continue;
            }

            $imei = $row['imei'] ?? null;
            if (! is_string($imei) || $imei === '') {
                $this->warn("Skipping index {$index}: missing imei.");
                $skipped++;

                continue;
            }

            $email = $row['media_owner_email'] ?? null;
            if (! is_string($email) || $email === '') {
                $email = $defaultEmail;
            }
            if ($email === null || $email === '') {
                $this->error("Row imei={$imei}: no media_owner_email and no --default-media-owner-email.");
                $skipped++;

                continue;
            }

            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $this->error("Row imei={$imei}: no user with email {$email} on this database.");
                $skipped++;

                continue;
            }

            $colorKey = $row['color_key'] ?? null;
            if ($colorKey === null && isset($row['color']) && is_string($row['color'])) {
                $needle = strtolower(trim($row['color']));
                foreach (config('vehicle.colors', []) as $key => $label) {
                    if (strtolower((string) $label) === $needle) {
                        $colorKey = $key;
                        break;
                    }
                }
                $colorKey ??= 'other';
            }
            if (is_string($colorKey) && $colorKey !== '' && ! array_key_exists($colorKey, config('vehicle.colors', []))) {
                $colorKey = 'other';
            }

            $status = (string) ($row['status'] ?? Vehicle::STATUS_ACTIVE);
            if (! in_array($status, Vehicle::STATUSES, true)) {
                $status = Vehicle::STATUS_ACTIVE;
            }

            $attributes = [
                'media_owner_id' => $user->id,
                'brand' => (string) ($row['brand'] ?? ''),
                'model' => (string) ($row['model'] ?? ''),
                'year' => isset($row['year']) ? (int) $row['year'] : null,
                'color_key' => $colorKey,
                'quantity' => isset($row['quantity']) ? max(1, (int) $row['quantity']) : 1,
                'image_path' => $row['image_path'] ?? null,
                'status' => $status,
                'notes' => $row['notes'] ?? null,
                'telemetry_pull_enabled' => (bool) ($row['telemetry_pull_enabled'] ?? true),
            ];

            if ($dry) {
                $this->line("[dry-run] updateOrCreate imei={$imei} owner={$email} {$attributes['brand']} {$attributes['model']}");
                $imported++;

                continue;
            }

            Vehicle::query()->updateOrCreate(
                ['imei' => $imei],
                $attributes
            );
            $imported++;
        }

        $this->info(($dry ? '[dry-run] ' : '')."Processed {$imported} row(s), skipped {$skipped}.");

        $total = count($decoded['vehicles']);

        return $imported === 0 && $total > 0 ? self::FAILURE : self::SUCCESS;
    }
}
