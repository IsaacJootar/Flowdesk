<?php

use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\Adapters\NullProviderWebhookVerifier;
use App\Services\Execution\Adapters\NullSubscriptionBillingAdapter;

return [
    // Used when provider key is missing or unknown.
    'fallback_provider' => env('FLOWDESK_EXECUTION_FALLBACK_PROVIDER', 'null'),

    // Provider adapter map. Real providers can be plugged in by replacing class bindings here.
    'providers' => [
        'null' => [
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => NullProviderWebhookVerifier::class,
        ],

        // Placeholder key currently used in tenant execution mode examples.
        // It intentionally maps to null adapters until a real provider adapter is added.
        'manual_ops' => [
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => NullProviderWebhookVerifier::class,
        ],
    ],
];
