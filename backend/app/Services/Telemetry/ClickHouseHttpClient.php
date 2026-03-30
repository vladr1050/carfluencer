<?php

namespace App\Services\Telemetry;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal ClickHouse HTTP interface (FORMAT JSONEachRow).
 * Large responses are read from the wire in chunks so we do not duplicate the full body in RAM.
 */
class ClickHouseHttpClient
{
    /**
     * Decode JSONEachRow into batches and pass each batch to $onBatch (bounded memory for big pages).
     *
     * @param  callable(list<array<string, mixed>>):void  $onBatch
     */
    public function consumeJsonEachRowBatches(string $sql, int $batchSize, callable $onBatch): void
    {
        $batchSize = max(100, min(50_000, $batchSize));

        [$url, $request] = $this->pendingRequest();

        $response = $request
            ->withOptions(['stream' => true])
            ->withBody($sql."\n", 'text/plain')
            ->post($url);

        if (! $response->successful()) {
            $this->logHttpError($response);

            throw new RuntimeException('ClickHouse HTTP error: '.$response->status());
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $batch = [];

        while (! $stream->eof()) {
            $buffer .= $stream->read(65_536);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
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
                    $batch[] = $decoded;
                }
                if (count($batch) >= $batchSize) {
                    $onBatch($batch);
                    $batch = [];
                }
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            try {
                $decoded = json_decode($tail, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Log::warning('ClickHouse JSONEachRow parse error (tail)', ['line' => mb_substr($tail, 0, 200)]);
                $decoded = null;
            }
            if (is_array($decoded)) {
                $batch[] = $decoded;
            }
        }

        if ($batch !== []) {
            $onBatch($batch);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queryJsonEachRow(string $sql): array
    {
        $rows = [];
        $batchSize = (int) config('telemetry.clickhouse.json_each_row_stream_batch', 2500);
        $this->consumeJsonEachRowBatches($sql, $batchSize, function (array $batch) use (&$rows): void {
            array_push($rows, ...$batch);
        });

        return $rows;
    }

    /**
     * @return array{0: string, 1: PendingRequest}
     */
    private function pendingRequest(): array
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

        $timeout = (int) config('telemetry.clickhouse.http_timeout_seconds', 900);
        $request = Http::timeout($timeout)->connectTimeout(min(30, $timeout))->acceptJson();
        if (is_string($user) && $user !== '') {
            $request = $request->withBasicAuth($user, (string) $pass);
        }

        return [$url, $request];
    }

    private function logHttpError(Response $response): void
    {
        $snippet = '';
        try {
            $body = $response->toPsrResponse()->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            while (! $body->eof() && strlen($snippet) < 800) {
                $snippet .= $body->read(4096);
            }
        } catch (\Throwable) {
            $snippet = $response->body();
        }

        Log::warning('ClickHouse query failed', [
            'status' => $response->status(),
            'body' => mb_substr($snippet, 0, 500),
        ]);
    }
}
