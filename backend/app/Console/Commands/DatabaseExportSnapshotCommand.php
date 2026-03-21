<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Writes a portable snapshot of the current default DB into storage/app/db-snapshots/.
 * PostgreSQL: custom-format dump (pg_restore). SQLite: copy of the .sqlite file.
 */
class DatabaseExportSnapshotCommand extends Command
{
    protected $signature = 'db:export-snapshot
                            {--connection= : Database connection name (default: env DB_CONNECTION)}
                            {--output-dir= : Override output directory (default: storage/app/db-snapshots)}';

    protected $description = 'Export full database snapshot for cloning to another server (see docs/OPERATIONS/03_full_database_sync.md).';

    public function handle(): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $config = config("database.connections.{$connectionName}");

        if (! is_array($config) || ! isset($config['driver'])) {
            $this->error("Unknown database connection: {$connectionName}");

            return self::FAILURE;
        }

        $driver = (string) $config['driver'];
        $outputDir = (string) ($this->option('output-dir') ?: storage_path('app/db-snapshots'));
        File::ensureDirectoryExists($outputDir);

        $stamp = now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            return $this->exportSqlite($config, $outputDir, $stamp);
        }

        if ($driver === 'pgsql') {
            return $this->exportPostgres($config, $outputDir, $stamp);
        }

        $this->error("Snapshot export is only implemented for sqlite and pgsql (got: {$driver}). Use manual pg_dump / mysqldump.");

        return self::FAILURE;
    }

    private function exportSqlite(array $config, string $outputDir, string $stamp): int
    {
        $path = (string) ($config['database'] ?? '');

        if ($path === '' || str_contains($path, ':memory:')) {
            $this->error('SQLite snapshot requires a file database (not :memory:). Set DB_DATABASE to a path like database/database.sqlite in .env.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("SQLite file not found: {$path}");

            return self::FAILURE;
        }

        $target = $outputDir.DIRECTORY_SEPARATOR."snapshot-sqlite_{$stamp}.sqlite";
        if (! copy($path, $target)) {
            $this->error("Failed to copy to {$target}");

            return self::FAILURE;
        }

        $this->writeSidecar($target, 'sqlite');
        $this->info("SQLite snapshot: {$target}");
        $this->line('On PostgreSQL production use pgloader (see docs/OPERATIONS/03_full_database_sync.md) or switch local DB to PostgreSQL and re-export.');

        return self::SUCCESS;
    }

    private function exportPostgres(array $config, string $outputDir, string $stamp): int
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '' || $username === '') {
            $this->error('PostgreSQL config missing database or username.');

            return self::FAILURE;
        }

        $target = $outputDir.DIRECTORY_SEPARATOR."snapshot-pgsql_{$stamp}.dump";

        $previous = getenv('PGPASSWORD');
        putenv('PGPASSWORD='.$password);

        $process = new Process(
            [
                'pg_dump',
                '-h', $host,
                '-p', $port,
                '-U', $username,
                '-d', $database,
                '-Fc',
                '--no-owner',
                '--no-acl',
                '-f', $target,
            ]
        );
        $process->setTimeout(3600);
        $process->run();

        if ($previous !== false) {
            putenv('PGPASSWORD='.$previous);
        } else {
            putenv('PGPASSWORD');
        }

        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed. Install PostgreSQL client tools (e.g. brew install libpq / postgresql-client).');
            $this->line($process->getErrorOutput());
            $this->line($process->getOutput());

            return self::FAILURE;
        }

        $this->writeSidecar($target, 'pgsql');
        $this->info("PostgreSQL snapshot: {$target}");
        $this->line('Copy to server and run deploy/restore-postgres-snapshot.sh.example (see docs).');

        return self::SUCCESS;
    }

    private function writeSidecar(string $snapshotPath, string $kind): void
    {
        $meta = [
            'kind' => $kind,
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'docs' => 'docs/OPERATIONS/03_full_database_sync.md',
        ];
        File::put($snapshotPath.'.meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    }
}
