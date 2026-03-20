<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelemetryTestClickhouseCommand extends Command
{
    protected $signature = 'telemetry:test-clickhouse
                            {--url= : Override base URL (default: TELEMETRY_CLICKHOUSE_URL)}';

    protected $description = 'Test HTTP connectivity to ClickHouse (GET /ping + SELECT 1).';

    public function handle(): int
    {
        $cfg = config('telemetry.clickhouse');
        $base = rtrim((string) ($this->option('url') ?: $cfg['base_url']), '/');
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        $database = (string) ($cfg['database'] ?? 'default');

        $this->info("Testing ClickHouse at: {$base}");

        $client = Http::timeout(15)->connectTimeout(5);
        if ($user !== '') {
            $client = $client->withBasicAuth($user, $pass);
        }

        try {
            $ping = $client->get($base.'/ping');
            if ($ping->successful() && str_contains($ping->body(), 'Ok')) {
                $this->line('  <info>✓</info> GET /ping — OK');
            } else {
                $this->comment('  GET /ping — HTTP '.$ping->status().' (continuing with SQL check)');
            }
        } catch (\Throwable $e) {
            $this->comment('  GET /ping — '.$e->getMessage().' (continuing with SQL check)');
        }

        return $this->runSqlProbe($client, $base, $database);
    }

    private function runSqlProbe(\Illuminate\Http\Client\PendingRequest $client, string $base, string $database): int
    {
        $url = $base.'/?'.http_build_query([
            'database' => $database,
            'default_format' => 'JSONEachRow',
        ]);

        try {
            $r = $client->withBody("SELECT 1 AS ok FORMAT JSONEachRow\n", 'text/plain')->post($url);
            if (! $r->successful()) {
                $this->error('  SELECT 1 — HTTP '.$r->status());
                $this->line(mb_substr($r->body(), 0, 500));

                return self::FAILURE;
            }
            $this->line('  <info>✓</info> SELECT 1 — '.trim($r->body()));

            $r2 = $client->withBody("SELECT version() AS version FORMAT JSONEachRow\n", 'text/plain')->post($url);
            if ($r2->successful() && trim($r2->body()) !== '') {
                $this->line('  <info>✓</info> '.trim($r2->body()));
            }

            $table = (string) config('telemetry.clickhouse.locations_table');
            $safeTable = preg_match('/^[a-zA-Z0-9_]+$/', $table) ? $table : '';
            $safeDb = preg_match('/^[a-zA-Z0-9_]+$/', $database) ? $database : 'default';
            if ($safeTable !== '') {
                $checkSql = "SELECT name FROM system.tables WHERE database = '{$safeDb}' AND name = '{$safeTable}' LIMIT 1 FORMAT JSONEachRow\n";
                $r3 = $client->withBody($checkSql, 'text/plain')->post($url);
                if ($r3->successful() && trim($r3->body()) !== '') {
                    $this->line("  <info>✓</info> Table `{$safeTable}` exists in database `{$safeDb}`");
                } else {
                    $this->comment("  Table `{$safeTable}` not found in `{$safeDb}` — set TELEMETRY_CLICKHOUSE_LOCATIONS_TABLE if different.");
                }
            }

            $this->newLine();
            $this->info('ClickHouse HTTP interface OK. Enable sync: TELEMETRY_CLICKHOUSE_ENABLED=true');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('SQL probe failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
