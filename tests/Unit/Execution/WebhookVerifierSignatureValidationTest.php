<?php

namespace Tests\Unit\Execution;

use App\Services\Execution\Adapters\FlutterwaveWebhookVerifier;
use App\Services\Execution\Adapters\PaystackWebhookVerifier;
use App\Services\Execution\DTO\ProviderWebhookPayloadData;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WebhookVerifierSignatureValidationTest extends TestCase
{
    public function test_paystack_verifier_accepts_signature_from_sandbox_secret(): void
    {
        config()->set('execution.providers.paystack.secret_key', '');
        config()->set('execution.providers.paystack.sandbox_secret_key', 'sandbox-paystack-secret');

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 'evt-001', 'reference' => 'ref-001', 'status' => 'success'],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha512', $body, 'sandbox-paystack-secret');

        $result = (new PaystackWebhookVerifier())->verify(new ProviderWebhookPayloadData(
            provider: 'paystack',
            body: $body,
            headers: ['x-paystack-signature' => $signature],
            signature: null,
            receivedAt: CarbonImmutable::now(),
        ));

        $this->assertTrue($result->valid);
        $this->assertSame('billing.settled', $result->eventType);
    }

    public function test_paystack_verifier_rejects_invalid_signature_when_signing_secret_exists(): void
    {
        config()->set('execution.providers.paystack.secret_key', 'live-secret');

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 'evt-002', 'reference' => 'ref-002', 'status' => 'success'],
        ], JSON_THROW_ON_ERROR);

        $result = (new PaystackWebhookVerifier())->verify(new ProviderWebhookPayloadData(
            provider: 'paystack',
            body: $body,
            headers: ['x-paystack-signature' => 'invalid-signature'],
            signature: null,
            receivedAt: CarbonImmutable::now(),
        ));

        $this->assertFalse($result->valid);
        $this->assertSame('Invalid Paystack webhook signature.', $result->reason);
    }

    public function test_flutterwave_verifier_accepts_sandbox_verif_hash(): void
    {
        config()->set('execution.providers.flutterwave.webhook_secret_hash', '');
        config()->set('execution.providers.flutterwave.sandbox_webhook_secret_hash', 'sandbox-hash');

        $body = json_encode([
            'event' => 'transfer.completed',
            'data' => ['id' => 'evt-003', 'status' => 'successful'],
        ], JSON_THROW_ON_ERROR);

        $result = (new FlutterwaveWebhookVerifier())->verify(new ProviderWebhookPayloadData(
            provider: 'flutterwave',
            body: $body,
            headers: ['verif-hash' => 'sandbox-hash'],
            signature: null,
            receivedAt: CarbonImmutable::now(),
        ));

        $this->assertTrue($result->valid);
        $this->assertSame('payout.settled', $result->eventType);
    }
}