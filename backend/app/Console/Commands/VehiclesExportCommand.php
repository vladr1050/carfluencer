<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VehiclesExportCommand extends Command
{
    protected $signature = 'vehicles:export
                            {--path=storage/app/vehicles-export.json : Output file path (relative to base_path or absolute)}';

    protected $description = 'Export all vehicles to JSON for import on another environment (uses media owner email for FK remap).';

    public function handle(): int
    {
        $rawPath = (string) $this->option('path');
        $path = str_starts_with($rawPath, DIRECTORY_SEPARATOR) ? $rawPath : base_path($rawPath);
        File::ensureDirectoryExists(dirname($path));

        $rows = Vehicle::query()
            ->with('mediaOwner:id,email')
            ->orderBy('id')
            ->get()
            ->map(function (Vehicle $v): array {
                $email = $v->mediaOwner?->email;
                if ($email === null || $email === '') {
                    $this->warn("Vehicle id={$v->id} imei={$v->imei} has no media owner email — import will need --default-media-owner-email.");
                }

                return [
                    'media_owner_email' => $email,
                    'brand' => $v->brand,
                    'model' => $v->model,
                    'year' => $v->year,
                    'color' => $v->color,
                    'quantity' => $v->quantity,
                    'image_path' => $v->image_path,
                    'imei' => $v->imei,
                    'status' => $v->status,
                    'notes' => $v->notes,
                    'telemetry_pull_enabled' => $v->telemetry_pull_enabled,
                ];
            })
            ->values()
            ->all();

        $payload = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'vehicles' => $rows,
        ];

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

        $this->info('Exported '.count($rows).' vehicle(s) to '.$path);

        return self::SUCCESS;
    }
}
