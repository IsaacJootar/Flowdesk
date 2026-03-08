<?php

namespace Tests\Unit\Execution;

use App\Services\Execution\Adapters\PaystackPayoutExecutionAdapter;
use App\Services\Execution\Adapters\PaystackSubscriptionBillingAdapter;
use App\Services\Execution\DTO\PayoutExecutionRequestData;
use App\Services\Execution\DTO\SubscriptionBillingRequestData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackReferenceNormalizationTest extends TestCase
{
    public function test_paystack_payout_reference_is_normalized_before_transfer_request(): void
    {
        config()->set('execution.providers.paystack.secret_key', 'sk_test_placeholder');
        config()->set('execution.providers.paystack.base_url', 'https://api.paystack.co');

        Http::fake([
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => ['recipient_code' => 'RCP_test_001'],
            ], 200),
            'https://api.paystack.co/transfer' => function (Request $request) {
                $payload = $request->data();

                $this->assertSame('request-4-payout', (string) ($payload['reference'] ?? ''));

                return Http::response([
                    'status' => true,
                    'message' => 'Transfer queued.',
                    'data' => [
                        'reference' => (string) ($payload['reference'] ?? ''),
                        'status' => 'success',
                        'transfer_code' => 'TRF_test_001',
                    ],
                ], 200);
            },
        ]);

        $adapter = new PaystackPayoutExecutionAdapter();
        $response = $adapter->executePayout(new PayoutExecutionRequestData(
            companyId: 1,
            requestId: 4,
            amount: 1200,
            currencyCode: 'NGN',
            channel: 'bank_transfer',
            beneficiary: [
                'name' => 'Test Vendor',
                'account_number' => '0000000000',
                'bank_code' => '057',
            ],
            idempotencyKey: 'request:4:payout',
            narration: 'Payout run',
            metadata: []
        ));

        $this->assertTrue($response->result->success);
        $this->assertSame('request-4-payout', (string) $response->result->providerReference);
    }

    public function test_paystack_billing_reference_is_normalized_but_metadata_idempotency_stays_raw(): void
    {
        config()->set('execution.providers.paystack.secret_key', 'sk_test_placeholder');
        config()->set('execution.providers.paystack.base_url', 'https://api.paystack.co');

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => function (Request $request) {
                $payload = $request->data();

                $this->assertSame('tenant-1-subscription-2-cycle-2026-03', (string) ($payload['reference'] ?? ''));
                $this->assertSame(
                    'tenant:1:subscription:2:cycle:2026-03',
                    (string) (($payload['metadata']['idempotency_key'] ?? ''))
                );

                return Http::response([
                    'status' => true,
                    'message' => 'Initialized.',
                    'data' => [
                        'reference' => (string) ($payload['reference'] ?? ''),
                        'authorization_url' => 'https://example.test/authorize',
                        'access_code' => 'AC_test_001',
                    ],
                ], 200);
            },
        ]);

        $adapter = new PaystackSubscriptionBillingAdapter();
        $response = $adapter->billTenant(new SubscriptionBillingRequestData(
            companyId: 1,
            subscriptionId: 2,
            planCode: 'pro',
            amount: 9500,
            currencyCode: 'NGN',
            periodStart: CarbonImmutable::parse('2026-03-01'),
            periodEnd: CarbonImmutable::parse('2026-03-31'),
            idempotencyKey: 'tenant:1:subscription:2:cycle:2026-03',
            metadata: [
                'customer_email' => 'finance@example.test',
            ],
        ));

        $this->assertTrue($response->result->success);
        $this->assertSame('tenant-1-subscription-2-cycle-2026-03', (string) $response->result->providerReference);
    }
}
