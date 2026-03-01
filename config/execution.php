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
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class, // just placeholders for now, not real class files
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => ManualOpsWebhookVerifier::class,
            'webhook_secret' => env('FLOWDESK_MANUAL_OPS_WEBHOOK_SECRET', ''),
        ],

        // Uncomment when ready to use Paystack provider adapters.
        // 'paystack' => [
        //     'subscription_billing_adapter' => \App\Services\Execution\Adapters\PaystackSubscriptionBillingAdapter::class,
        //     'payout_execution_adapter' => \App\Services\Execution\Adapters\PaystackPayoutExecutionAdapter::class,
        //     'webhook_verifier' => \App\Services\Execution\Adapters\PaystackWebhookVerifier::class,
        //     'base_url' => env('FLOWDESK_PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        //     'secret_key' => env('FLOWDESK_PAYSTACK_SECRET_KEY', ''),
        // ],

        // Uncomment when ready to use Flutterwave provider adapters.
        // 'flutterwave' => [
        //     'subscription_billing_adapter' => \App\Services\Execution\Adapters\FlutterwaveSubscriptionBillingAdapter::class,
        //     'payout_execution_adapter' => \App\Services\Execution\Adapters\FlutterwavePayoutExecutionAdapter::class,
        //     'webhook_verifier' => \App\Services\Execution\Adapters\FlutterwaveWebhookVerifier::class,
        //     'base_url' => env('FLOWDESK_FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        //     'secret_key' => env('FLOWDESK_FLUTTERWAVE_SECRET_KEY', ''),
        //     'webhook_secret_hash' => env('FLOWDESK_FLUTTERWAVE_WEBHOOK_SECRET_HASH', ''),
        //     'redirect_url' => env('FLOWDESK_FLUTTERWAVE_REDIRECT_URL', ''),
        // ],
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

    'ops_alerts' => [
        'window_minutes' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_WINDOW_MINUTES', 60),
        'failure_threshold' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_FAILURE_THRESHOLD', 5),
    ],
];
