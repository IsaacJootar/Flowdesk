<?php

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrelationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_requests_receive_a_correlation_id_header(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Correlation-ID');
        $this->assertNotSame('', (string) $response->headers->get('X-Correlation-ID'));
    }
}
