<?php

use App\Services\Execution\Adapters\FlutterwavePayoutExecutionAdapter;
use App\Services\Execution\Adapters\FlutterwaveSubscriptionBillingAdapter;
use App\Services\Execution\Adapters\FlutterwaveWebhookVerifier;
use App\Services\Execution\Adapters\ManualOpsWebhookVerifier;
use App\Services\Execution\Adapters\MonoPayoutExecutionAdapter;
use App\Services\Execution\Adapters\MonoSubscriptionBillingAdapter;
use App\Services\Execution\Adapters\MonoWebhookVerifier;
use App\Services\Execution\Adapters\NullPayoutExecutionAdapter;
use App\Services\Execution\Adapters\NullProviderWebhookVerifier;
use App\Services\Execution\Adapters\NullSubscriptionBillingAdapter;
use App\Services\Execution\Adapters\PaystackPayoutExecutionAdapter;
use App\Services\Execution\Adapters\PaystackSubscriptionBillingAdapter;
use App\Services\Execution\Adapters\PaystackWebhookVerifier;

$rolloutPilotCompanySlugs = array_values(array_unique(array_filter(array_map(
    static fn (string $slug): string => strtolower(trim($slug)),
    explode(',', (string) env('FLOWDESK_RAILS_PILOT_COMPANY_SLUGS', ''))
))));

$rolloutGoLiveCompanySlugs = array_values(array_unique(array_filter(array_map(
    static fn (string $slug): string => strtolower(trim($slug)),
    explode(',', (string) env('FLOWDESK_RAILS_GO_LIVE_COMPANY_SLUGS', ''))
))));

return [
    // Used when provider key is missing or unknown.
    'fallback_provider' => env('FLOWDESK_EXECUTION_FALLBACK_PROVIDER', 'null'),

    // Provider adapter map. Tenant execution mode selects providers from this registry.
    'providers' => [
        'null' => [
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => NullProviderWebhookVerifier::class,
        ],

        'manual_ops' => [
            'subscription_billing_adapter' => NullSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => NullPayoutExecutionAdapter::class,
            'webhook_verifier' => ManualOpsWebhookVerifier::class,
            'webhook_secret' => env('FLOWDESK_MANUAL_OPS_WEBHOOK_SECRET', ''),
        ],

        'paystack' => [
            'subscription_billing_adapter' => PaystackSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => PaystackPayoutExecutionAdapter::class,
            'webhook_verifier' => PaystackWebhookVerifier::class,
            'base_url' => env('FLOWDESK_PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'secret_key' => env('FLOWDESK_PAYSTACK_SECRET_KEY', ''),
            'webhook_secret' => env('FLOWDESK_PAYSTACK_WEBHOOK_SECRET', ''),
            'sandbox_base_url' => env('FLOWDESK_PAYSTACK_SANDBOX_BASE_URL', 'https://api.paystack.co'),
            'sandbox_secret_key' => env('FLOWDESK_PAYSTACK_SANDBOX_SECRET_KEY', ''),
            'sandbox_webhook_secret' => env('FLOWDESK_PAYSTACK_SANDBOX_WEBHOOK_SECRET', ''),
        ],

        'flutterwave' => [
            'subscription_billing_adapter' => FlutterwaveSubscriptionBillingAdapter::class,
            'payout_execution_adapter' => FlutterwavePayoutExecutionAdapter::class,
            'webhook_verifier' => FlutterwaveWebhookVerifier::class,
            'base_url' => env('FLOWDESK_FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            'secret_key' => env('FLOWDESK_FLUTTERWAVE_SECRET_KEY', ''),
            'webhook_secret_hash' => env('FLOWDESK_FLUTTERWAVE_WEBHOOK_SECRET_HASH', ''),
            'sandbox_base_url' => env('FLOWDESK_FLUTTERWAVE_SANDBOX_BASE_URL', 'https://api.flutterwave.com/v3'),
            'sandbox_secret_key' => env('FLOWDESK_FLUTTERWAVE_SANDBOX_SECRET_KEY', ''),
            'sandbox_webhook_secret_hash' => env('FLOWDESK_FLUTTERWAVE_SANDBOX_WEBHOOK_SECRET_HASH', ''),
            'redirect_url' => env('FLOWDESK_FLUTTERWAVE_REDIRECT_URL', ''),
        ],

        // Mono — Open Banking + Disbursements + DirectPay
        // Payouts:        POST /v2/disbursements  (major-unit NGN amounts, built-in account verification)
        // Billing:        POST /v1/payments/initiate  (DirectPay — mandate-based bank debit, no card needed)
        // Connect:        GET  /v2/accounts/{id}/transactions  (live bank feed, replaces CSV import)
        // Account lookup: POST /v1/lookup/account-number  (pre-payout verification)
        // Webhook header: mono-webhook-secret (HMAC-SHA512)
        'mono' => [
            'subscription_billing_adapter' => MonoSubscriptionBillingAdapter::class,
            'payout_execution_adapter'     => MonoPayoutExecutionAdapter::class,
            'webhook_verifier'             => MonoWebhookVerifier::class,
            'base_url'                     => env('FLOWDESK_MONO_BASE_URL', 'https://api.withmono.com'),
            'secret_key'                   => env('FLOWDESK_MONO_SECRET_KEY', ''),
            'webhook_secret'               => env('FLOWDESK_MONO_WEBHOOK_SECRET', ''),
            'sandbox_base_url'             => env('FLOWDESK_MONO_SANDBOX_BASE_URL', 'https://api.withmono.com'),
            'sandbox_secret_key'           => env('FLOWDESK_MONO_SANDBOX_SECRET_KEY', ''),
            'sandbox_webhook_secret'       => env('FLOWDESK_MONO_SANDBOX_WEBHOOK_SECRET', ''),
            'redirect_url'                 => env('FLOWDESK_MONO_REDIRECT_URL', ''),
        ],
    ],

    // Staged rollout controls for real providers.
    'rails_rollout' => [
        'default_provider' => strtolower(trim((string) env('FLOWDESK_RAILS_DEFAULT_PROVIDER', 'manual_ops'))),
        'pilot_company_slugs' => $rolloutPilotCompanySlugs,
        'go_live_company_slugs' => $rolloutGoLiveCompanySlugs,
        'allow_external_provider_without_pilot' => filter_var(env('FLOWDESK_RAILS_ALLOW_EXTERNAL_WITHOUT_PILOT', false), FILTER_VALIDATE_BOOL),
    ],

    // subscription auto-billing defaults.
    'billing' => [
        'default_currency' => env('FLOWDESK_BILLING_DEFAULT_CURRENCY', 'NGN'),
        'plan_amounts' => [
            'pilot' => (float) env('FLOWDESK_PLAN_AMOUNT_PILOT', 0),
            'growth' => (float) env('FLOWDESK_PLAN_AMOUNT_GROWTH', 0),
            'business' => (float) env('FLOWDESK_PLAN_AMOUNT_BUSINESS', 0),
            'enterprise' => (float) env('FLOWDESK_PLAN_AMOUNT_ENTERPRISE', 0),
        ],
    ],

    'ops_alerts' => [
        'window_minutes' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_WINDOW_MINUTES', 60),
        'failure_threshold' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_FAILURE_THRESHOLD', 5),
        'stuck_queued_older_than_minutes' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_STUCK_OLDER_THAN_MINUTES', 45),
        'stuck_queued_threshold' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_STUCK_THRESHOLD', 10),
        'invalid_webhook_threshold' => (int) env('FLOWDESK_EXECUTION_OPS_ALERT_INVALID_WEBHOOK_THRESHOLD', 5),
    ],

    'ops_recovery' => [
        'enabled' => filter_var(env('FLOWDESK_EXECUTION_OPS_AUTO_RECOVERY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'older_than_minutes' => (int) env('FLOWDESK_EXECUTION_OPS_AUTO_RECOVERY_OLDER_THAN_MINUTES', 30),
        'max_per_pipeline' => (int) env('FLOWDESK_EXECUTION_OPS_AUTO_RECOVERY_MAX_PER_PIPELINE', 200),
        'cooldown_minutes' => (int) env('FLOWDESK_EXECUTION_OPS_AUTO_RECOVERY_COOLDOWN_MINUTES', 15),
    ],
];