<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssignRequestIdMiddlewareTest extends TestCase
{
    public function test_health_endpoint_includes_x_request_id_header(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Request-ID');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_client_can_pass_x_request_id_through(): void
    {
        $response = $this->withHeaders([
            'X-Request-ID' => 'trace-abc-123',
        ])->get('/up');

        $response->assertOk();
        $this->assertSame('trace-abc-123', $response->headers->get('X-Request-ID'));
    }
}
