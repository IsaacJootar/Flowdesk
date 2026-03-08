# Flowdesk Real Provider Integration Guide

This guide explains provider plug points, adapter usage, manual operations flow, and where each status/toast appears in UI.
For full module-level implementation inventory, see `FLOWDESK_MODULE_STATUS.md`.

## 1) Exact Plug Points (Files)

## Core interfaces (provider contract)
1. `app/Services/Execution/Contracts/SubscriptionBillingAdapterInterface.php`
2. `app/Services/Execution/Contracts/PayoutExecutionAdapterInterface.php`
3. `app/Services/Execution/Contracts/ProviderWebhookVerifierInterface.php`

## DTO payload/result layer
1. `app/Services/Execution/DTO/SubscriptionBillingRequestData.php`
2. `app/Services/Execution/DTO/PayoutExecutionRequestData.php`
3. `app/Services/Execution/DTO/ProviderWebhookPayloadData.php`
4. `app/Services/Execution/DTO/SubscriptionBillingResponseData.php`
5. `app/Services/Execution/DTO/PayoutExecutionResponseData.php`
6. `app/Services/Execution/DTO/AdapterOperationResult.php`
7. `app/Services/Execution/DTO/AdapterOperationStatus.php`
8. `app/Services/Execution/DTO/AdapterErrorData.php`
9. `app/Services/Execution/DTO/WebhookVerificationResultData.php`
10. `app/Services/Execution/DTO/ExecutionRetryMetadata.php`

## Provider resolution and runtime wiring
1. `app/Services/Execution/ExecutionAdapterRegistry.php`
2. `app/Services/Execution/TenantExecutionAdapterFactory.php`
3. `config/execution.php`

`TenantExecutionAdapterFactory` selects adapter implementation by tenant mode/provider.

## Execution processors and reconciliation
1. `app/Services/Execution/SubscriptionAutoBillingOrchestrator.php`
2. `app/Services/Execution/SubscriptionBillingAttemptProcessor.php`
3. `app/Services/Execution/RequestPayoutExecutionAttemptProcessor.php`
4. `app/Services/Execution/SubscriptionBillingWebhookReconciliationService.php`
5. `app/Services/Execution/RequestPayoutWebhookReconciliationService.php`

## Webhook endpoint
1. Route: `routes/web.php` (`POST /webhooks/execution/{provider}`)
2. Controller: `app/Http/Controllers/ExecutionWebhookController.php`

## Platform setup UI and Ops
1. `app/Livewire/Platform/TenantExecutionModePage.php`
2. `resources/views/livewire/platform/tenant-execution-mode-page.blade.php`
3. `app/Livewire/Platform/ExecutionOperationsPage.php`
4. `resources/views/livewire/platform/execution-operations-page.blade.php`
5. `app/Livewire/Platform/IncidentHistoryPage.php`
6. `resources/views/livewire/platform/incident-history-page.blade.php`

## Tenant-facing rails status page (non-technical)
1. Route: `/settings/payments-rails`
2. Entry: `app/Livewire/Settings/PaymentsRailsIntegrationPage.php`
3. Purpose: show business-friendly connection readiness (status, mode, provider key) without exposing raw API/webhook payload details.

---

## 2) Adapter Usage vs Guardrails

## What adapters do
1. Transform internal DTOs to provider API calls.
2. Return normalized operation results (`settled`, `failed`, `queued`, `skipped`, etc).
3. Verify/normalize webhook payloads.

## What adapters do not do
1. Global queue monitoring across tenants.
2. Recovery batching policy (up to 200 per click).
3. Operations-center reasoning toasts and breakdown summaries.
4. Alerting/reporting (`execution:ops:alert-summary`).

## Where guardrails live
1. Processors/reconciliation services apply state transitions.
2. `ExecutionOperationsPage` performs manual recovery, batch filters, and toast messaging.
3. Console/reporting commands provide cross-system reliability visibility.

---

## 3) End-to-End Runtime Flow

1. Request/billing event queues an execution attempt.
2. Processor builds DTO and resolves adapter via `TenantExecutionAdapterFactory`.
3. Adapter executes provider call and returns normalized result DTO.
4. Processor maps result to internal lifecycle (`queued`, `processing`, `webhook_pending`, `settled`, `failed`, `reversed`, `skipped`).
5. Webhook verifier validates callbacks and reconciliation services finalize attempt/request states.
6. Platform Ops can manually retry/recover/reconcile from Execution Operations UI.

---

## 4) Manual Ops Flow (Execution Operations)

Route: `http://127.0.0.1:8000/platform/operations/execution`
Incident timeline route: `http://127.0.0.1:8000/platform/operations/incident-history`

## Filter row layout (3 + 3)
1. Row 1: `Tenant`, `Provider`, `Pipeline`
2. Row 2: `Status`, `Display Age Filter (mins)`, `Display Scope`

## Recovery controls
1. `Recovery Note`
2. `Recovery Age Threshold (mins)`
3. Buttons:
   - `Run Billing Recovery`
   - `Run Payout Recovery`
   - `Run Webhook Recovery`

Helper note in UI:
`Recovery runs process up to 200 queued records per click. Age threshold uses queued time, not record creation time.`

## Incident dashboard cards in UI
1. `Failure Rate (Xm)`
2. `Skipped Rate (Xm)`
3. `Oldest Queue Age`
4. `Last Recovery Outcome`
5. `Auto Recovery Runs` table (timestamp, tenant, pipeline, provider, matched, processed, skipped, rejected)

## Runbook links in UI
1. Provider/config checks
2. Missing request
3. Missing subscription
4. State changed
5. Invalid verification
6. Missing linked attempt

## Recovery toast logic (matched vs processed)
1. `matched = 0`
   - `No queued <pipeline records> matched the recovery age threshold (X mins).`
2. `matched > 0 && processed = 0`
   - `Found N queued <pipeline records> older than X mins, but none were processed. Check provider/config/state and retry. Breakdown: ...`
3. `processed > 0`
   - `Processed P of N queued <pipeline records> older than X mins.`
   - If no-op provider caused skip outcomes: append `Y ended as skipped (no-op provider).`

## Breakdown keys by pipeline
1. Billing: `missing subscription`, `state changed`, `other`
2. Payout: `missing request`, `missing subscription`, `state changed`, `other`
3. Webhook: `invalid verification`, `missing linked attempt`, `state changed`, `other`

---

## 5) Why `skipped` Happens in Manual Recovery

`manual_ops`/null-style provider adapters intentionally avoid real transfer/billing side-effects.
A queued recovery can be successfully processed by the system but finalized as `skipped` to indicate no external provider execution was performed.

---

## 6) Enable a Real Provider (When Ready)

## Step A: Provider map is already active
`config/execution.php` now ships with active `manual_ops`, `paystack`, and `flutterwave` provider entries plus sandbox/live credential keys.

## Step B: Set env values
Example:

```env
FLOWDESK_EXECUTION_FALLBACK_PROVIDER=null

# Paystack
FLOWDESK_PAYSTACK_BASE_URL=https://api.paystack.co
FLOWDESK_PAYSTACK_SECRET_KEY=...

# Flutterwave
FLOWDESK_FLUTTERWAVE_BASE_URL=https://api.flutterwave.com/v3
FLOWDESK_FLUTTERWAVE_SECRET_KEY=...
FLOWDESK_FLUTTERWAVE_WEBHOOK_SECRET_HASH=...
FLOWDESK_FLUTTERWAVE_SANDBOX_WEBHOOK_SECRET_HASH=...
FLOWDESK_FLUTTERWAVE_REDIRECT_URL=https://your-domain.com/payments/flutterwave/redirect
```

Then run:

```bash
php artisan config:clear
```

## Step C: Set tenant execution provider in UI
1. Open `Platform -> Tenant Execution Mode`
2. Set `Payment Execution Mode = execution_enabled`
3. Set `Execution Provider = paystack` or `flutterwave`
4. Save

## Step D: Configure provider webhook callback
Set callback URL at provider dashboard:

```text
https://your-domain.com/webhooks/execution/{provider}
```

Examples:
1. `/webhooks/execution/paystack`
2. `/webhooks/execution/flutterwave`

---

## 7) End-to-End Test From UI (No Live Provider Yet)

Use default `manual_ops` to validate internal execution flow and operations tooling safely.

1. In `Platform -> Tenant Execution Mode`, set mode `execution_enabled`, provider `manual_ops`.
2. Create and approve a request through both approval scopes.
3. Open `Platform -> Execution Operations`, filter `Pipeline = payout`.
4. Run recovery/retry and verify toast + breakdown behavior.

Optional webhook simulation:

```bash
curl -X POST http://127.0.0.1:8000/webhooks/execution/manual_ops \
  -H "Content-Type: application/json" \
  -d "{\"event_id\":\"evt-local-001\",\"event_type\":\"payout.settled\",\"payout_attempt_id\":1,\"status\":\"settled\"}"
```

---

## 8) Local Commands

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work

php artisan tenants:billing:auto-charge
php artisan tenants:billing:process-queued --batch=500
php artisan execution:ops:alert-summary
php artisan execution:ops:auto-recover --dry-run
php artisan execution:ops:auto-recover --batch=100

php artisan test tests/Feature/Execution/SubscriptionAutoBillingPhaseThreeTest.php
php artisan test tests/Feature/Execution/RequestPayoutExecutionPhaseFourTest.php
php artisan test tests/Feature/Execution/ExecutionOperationsCenterPhaseFiveTest.php
```

---

## 9) Production Hardening Notes

1. Keep secrets in env/secret manager; do not store raw keys in DB.
2. Enforce webhook signature/hash verification per provider.
3. Keep idempotency keys stable per operation.
4. Use Ops Center retry/reconcile with reasons for audit traceability.
5. Monitor repeated failures with `execution:ops:alert-summary`.




---

## 10) Incident History UI

The dedicated Incident History page provides a cross-tenant incident ledger for execution operations.

1. Filters: tenant, pipeline, incident type, actor, date range.
2. Trend: seven-day pipeline chart (billing, payout, webhook, system).
3. Table: timestamp, tenant, pipeline, type, action, actor, details, metadata drill-down.
4. Export: CSV download using the active filters.


