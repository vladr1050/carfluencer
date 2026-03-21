<?php

namespace Tests\Feature;

use Tests\TestCase;

class TelemetryEnsureEnvCommandTest extends TestCase
{
    public function test_telemetry_ensure_env_exits_successfully(): void
    {
        $this->artisan('telemetry:ensure-env', ['--dry-run' => true])
            ->assertExitCode(0);
    }
}
