# Flowdesk Real Provider Integration Guide

This guide shows exactly where to plug in a real execution provider (for example Paystack, Flutterwave, Stripe), and which commands to run.

## 1) Integration Surface (Core Files)

### Contracts (what your provider must implement)
- `app/Services/Execution/Contracts/SubscriptionBillingAdapterInterface.php`
- `app/Services/Execution/Contracts/PayoutExecutionAdapterInterface.php`
- `app/Services/Execution/Contracts/ProviderWebhookVerifierInterface.php`

### Provider resolution and runtime wiring
- `app/Services/Execution/ExecutionAdapterRegistry.php`
- `app/Services/Execution/TenantExecutionAdapterFactory.php`
- `config/execution.php`

### Processing and orchestration
- `app/Services/Execution/SubscriptionAutoBillingOrchestrator.php`
- `app/Services/Execution/SubscriptionBillingAttemptProcessor.php`
- `app/Services/Execution/RequestPayoutExecutionAttemptProcessor.php`
- `app/Services/Execution/SubscriptionBillingWebhookReconciliationService.php`
- `app/Services/Execution/RequestPayoutWebhookReconciliationService.php`

### Webhook endpoint
- `routes/web.php` (`POST /webhooks/execution/{provider}`)
- `app/Http/Controllers/ExecutionWebhookController.php`

### Platform setup UI
- `app/Livewire/Platform/TenantExecutionModePage.php`
- `resources/views/livewire/platform/tenant-execution-mode-page.blade.php`

### Ops center (retry/reconcile)
- `app/Livewire/Platform/ExecutionOperationsPage.php`
- `resources/views/livewire/platform/execution-operations-page.blade.php`
- `app/Services/Execution/ExecutionWebhookManualReconciliationService.php`

---

## Prebuilt Provider Plugin Files (Added)

The following adapter plugins are already created in code and currently disabled by config comments:

### Paystack
1. pp/Services/Execution/Adapters/PaystackSubscriptionBillingAdapter.php
2. pp/Services/Execution/Adapters/PaystackPayoutExecutionAdapter.php
3. pp/Services/Execution/Adapters/PaystackWebhookVerifier.php

### Flutterwave
1. pp/Services/Execution/Adapters/FlutterwaveSubscriptionBillingAdapter.php
2. pp/Services/Execution/Adapters/FlutterwavePayoutExecutionAdapter.php
3. pp/Services/Execution/Adapters/FlutterwaveWebhookVerifier.php

To activate either provider, uncomment its block in config/execution.php under providers.

---
## 2) Step-by-Step: Add a Real Provider

## Step A: Create provider adapters
Create 3 classes in `app/Services/Execution/Adapters/`:
1. `YourProviderSubscriptionBillingAdapter.php`
2. `YourProviderPayoutExecutionAdapter.php`
3. `YourProviderWebhookVerifier.php`

They must implement:
1. `SubscriptionBillingAdapterInterface`
2. `PayoutExecutionAdapterInterface`
3. `ProviderWebhookVerifierInterface`

Map provider payloads to Flowdesk DTO responses:
1. `AdapterOperationResult`
2. `SubscriptionBillingResponseData`
3. `PayoutExecutionResponseData`
4. `WebhookVerificationResultData`

Use existing adapters as references:
1. `app/Services/Execution/Adapters/NullSubscriptionBillingAdapter.php`
2. `app/Services/Execution/Adapters/NullPayoutExecutionAdapter.php`
3. `app/Services/Execution/Adapters/ManualOpsWebhookVerifier.php`

## Step B: Register provider in config
Update `config/execution.php` under `providers`:

```php
'your_provider_key' => [
    'subscription_billing_adapter' => \App\Services\Execution\Adapters\YourProviderSubscriptionBillingAdapter::class,
    'payout_execution_adapter' => \App\Services\Execution\Adapters\YourProviderPayoutExecutionAdapter::class,
    'webhook_verifier' => \App\Services\Execution\Adapters\YourProviderWebhookVerifier::class,
    'webhook_secret' => env('FLOWDESK_YOUR_PROVIDER_WEBHOOK_SECRET', ''),
],
```

## Step C: Add environment secrets
Update `.env`:

```env
FLOWDESK_EXECUTION_FALLBACK_PROVIDER=null
FLOWDESK_YOUR_PROVIDER_WEBHOOK_SECRET=replace_me
FLOWDESK_YOUR_PROVIDER_API_KEY=replace_me
FLOWDESK_YOUR_PROVIDER_BASE_URL=https://api.provider.com
```

If you add new config keys, run:

```bash
php artisan config:clear
```

## Step D: Enable provider per tenant
In platform UI:
1. Open `Platform -> Tenant Execution Mode`
2. Set `Payment Execution Mode = execution_enabled`
3. Set `Execution Provider = your_provider_key`
4. Save

If mode cannot enable, verify tenant guardrails are satisfied:
1. active tenant lifecycle
2. current billing/subscription status
3. required module entitlements
4. default payment authorization workflow exists

## Step E: Provider webhook setup
Set provider callback URL to:

```text
https://your-domain.com/webhooks/execution/your_provider_key
```

Flowdesk route:
- `POST /webhooks/execution/{provider}`

Webhook flow:
1. `ExecutionWebhookController` accepts payload/signature
2. `SubscriptionBillingWebhookReconciliationService` verifies and reconciles
3. falls back to `RequestPayoutWebhookReconciliationService` when payload matches payout attempts

---

## 3) Commands for Local/Server Operations

## App runtime
```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
```

## Billing and execution pipelines
```bash
php artisan tenants:billing:auto-charge
php artisan tenants:billing:process-queued --batch=500
php artisan execution:ops:alert-summary
```

## Tests (execution tracks)
```bash
php artisan test tests/Feature/Execution/SubscriptionAutoBillingPhaseThreeTest.php
php artisan test tests/Feature/Execution/RequestPayoutExecutionPhaseFourTest.php
php artisan test tests/Feature/Execution/ExecutionOperationsCenterPhaseFiveTest.php
```

## Route check
```bash
php artisan route:list | findstr execution
```

---

## 4) Quick Smoke Test Sequence

1. Configure tenant as `execution_enabled` with your provider key.
2. Trigger billing orchestration (`tenants:billing:auto-charge`).
3. Process queue (`tenants:billing:process-queued`).
4. Confirm attempt row created in `tenant_subscription_billing_attempts`.
5. Send webhook callback from provider/sandbox to `/webhooks/execution/{provider}`.
6. Confirm status changes in billing/payout attempt tables.
7. Open `Platform -> Execution Operations` and verify pipeline rows.
8. If needed, use retry/reconcile actions with reason.

---

## 5) Where Provider Dropdown Values Come From Today

In `Platform -> Execution Operations`, the Provider filter is built from distinct `provider_key` values already present in:
1. billing attempts
2. payout attempts
3. webhook events

Source:
- `app/Livewire/Platform/ExecutionOperationsPage.php` (`providerOptions()`)

This means providers appear after records exist. If you want config-driven provider options, add a second source from `config('execution.providers')`.

---

## 6) Recommended Production Hardening

1. Use provider idempotency keys for both billing and payouts.
2. Enforce strict signature verification in webhook verifier.
3. Store only credential references in DB; keep secrets in env/secret manager.
4. Keep retries controlled via Ops Center and queue policies.
5. Monitor repeated failures with `execution:ops:alert-summary` and central logs.

