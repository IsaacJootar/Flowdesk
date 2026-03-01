<?php

use App\Services\Execution\Adapters\ManualOpsWebhookVerifier;
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

        // Placeholder provider for staged rollout.
        // Billing/payout are still no-op adapters until a real provider adapter is wired.
        'manual_ops' => [
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => ManualOpsWebhookVerifier::class,
            'webhook_secret' => env('FLOWDESK_MANUAL_OPS_WEBHOOK_SECRET', ''),
        ],
    ],

    // Phase 3 subscription auto-billing defaults.
    'billing' => [
        'default_currency' => env('FLOWDESK_BILLING_DEFAULT_CURRENCY', 'NGN'),
        'plan_amounts' => [
            // Keep pilot at zero by default unless platform chooses to bill pilots.
            'pilot' => (float) env('FLOWDESK_PLAN_AMOUNT_PILOT', 0),
            'growth' => (float) env('FLOWDESK_PLAN_AMOUNT_GROWTH', 0),
            'business' => (float) env('FLOWDESK_PLAN_AMOUNT_BUSINESS', 0),
            'enterprise' => (float) env('FLOWDESK_PLAN_AMOUNT_ENTERPRISE', 0),
        ],
    ],
];