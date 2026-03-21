<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseExportSnapshotCommandTest extends TestCase
{
    public function test_export_fails_when_sqlite_is_in_memory(): void
    {
        $this->assertSame(':memory:', (string) config('database.connections.sqlite.database'));

        $this->artisan('db:export-snapshot')
            ->expectsOutputToContain('not :memory:')
            ->assertFailed();
    }

    public function test_export_copies_file_based_sqlite(): void
    {
        $tmpDb = storage_path('app/test-snapshot-source.sqlite');
        if (File::exists($tmpDb)) {
            File::delete($tmpDb);
        }
        File::put($tmpDb, '');

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $tmpDb]);

        $outDir = storage_path('app/db-snapshots-test-'.uniqid('', true));
        File::ensureDirectoryExists($outDir);

        try {
            $this->artisan('db:export-snapshot', ['--output-dir' => $outDir])
                ->assertSuccessful();

            $files = glob($outDir.DIRECTORY_SEPARATOR.'snapshot-sqlite_*.sqlite');
            $this->assertIsArray($files);
            $this->assertCount(1, $files);
            $this->assertFileExists($files[0]);
            $this->assertFileExists($files[0].'.meta.json');
        } finally {
            File::deleteDirectory($outDir);
            if (File::exists($tmpDb)) {
                File::delete($tmpDb);
            }
        }

        // Вернуть конфиг для остальных тестов в процессе (phpunit изолирует приложение по сути на класс)
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }
}
