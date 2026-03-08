<?php

namespace Tests\Feature\Execution;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutionWebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_webhook_endpoint_is_rate_limited(): void
    {
        config()->set('security.rate_limits.execution_webhooks_per_minute', 2);

        $endpoint = route('webhooks.execution', ['provider' => 'manual_ops']);
        $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10']);

        $first = $client->postJson($endpoint, ['event' => 'first']);
        $second = $client->postJson($endpoint, ['event' => 'second']);
        $third = $client->postJson($endpoint, ['event' => 'third']);

        $this->assertNotSame(429, $first->status());
        $this->assertNotSame(429, $second->status());
        $third->assertStatus(429);
    }
}
