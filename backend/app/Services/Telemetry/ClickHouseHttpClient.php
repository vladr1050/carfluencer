<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal ClickHouse HTTP interface (FORMAT JSONEachRow).
 */
class ClickHouseHttpClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function queryJsonEachRow(string $sql): array
    {
        $cfg = config('telemetry.clickhouse');
        $baseUrl = $cfg['base_url'];
        $database = $cfg['database'];
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';

        $url = $baseUrl.'/?'.http_build_query([
            'database' => $database,
            'default_format' => 'JSONEachRow',
        ]);

        $timeout = (int) config('telemetry.clickhouse.http_timeout_seconds', 120);
        $request = Http::timeout($timeout)->connectTimeout(min(30, $timeout))->acceptJson();
        if (is_string($user) && $user !== '') {
            $request = $request->withBasicAuth($user, (string) $pass);
        }

        $response = $request->withBody($sql."\n", 'text/plain')->post($url);

        if (! $response->successful()) {
            Log::warning('ClickHouse query failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('ClickHouse HTTP error: '.$response->status());
        }

        $body = trim($response->body());
        if ($body === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::warning('ClickHouse JSONEachRow parse error', ['line' => mb_substr($line, 0, 200)]);

                continue;
            }
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }
}
