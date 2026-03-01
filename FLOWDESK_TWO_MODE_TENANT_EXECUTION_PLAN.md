# Flowdesk Two-Mode Tenant Execution Plan

## Objective
Allow each tenant organization to choose one of two operating modes:

1. `Decision-only` (current default)
2. `Execution-enabled` (optional advanced mode)

This keeps Flowdesk safe for conservative organizations while enabling full end-to-end payment execution for organizations that need it.

---

## Mode Definitions

### Mode A: Decision-only
- Flowdesk handles requests, approvals, controls, and audit.
- Final money movement is executed outside Flowdesk (bank portal / ERP / finance ops).
- Flowdesk stores decision status and traceability only.

### Mode B: Execution-enabled
- Flowdesk handles request-to-approval and also initiates payment execution via provider adapters.
- Settlement and failure updates come back via webhooks.
- Flowdesk becomes the operating layer for both decisions and execution history.

---

## Why Two Modes
- Risk separation: organizations can start with low-risk decision control.
- Compliance flexibility: execution can be enabled only after legal/compliance readiness.
- Product scalability: same workflow core supports both light and deep adoption.

---

## Current State (Implemented)
- Tenant lifecycle and plan governance.
- Billing ledger and offline/manual billing records.
- Billing status automation (`current`, `grace`, `overdue`, `suspended`).
- Seat governance and module gating.
- Tenant-level audit trail for platform admin actions.

This means Flowdesk is already production-usable in `Decision-only`.

---

## Proposed Data/Config Additions

### Tenant execution flags
- `billing_mode`: `manual | auto | hybrid`
- `payment_execution_mode`: `disabled | enabled`
- `execution_provider`: provider key (nullable)
- `execution_enabled_at`, `execution_enabled_by`

### Provider credential references
- Store references/tokens (not raw sensitive keys in plain tables).
- Per-tenant credential health and verification status.

### Execution policy controls
- Max per-transaction amount
- Daily/monthly execution caps
- Maker-checker requirement threshold
- Allowed channels (`bank_transfer`, `wallet_payout`, etc.)

---

## Architecture Approach

### 1. Adapter Layer (Provider-agnostic)
Create interfaces:
- `SubscriptionBillingAdapterInterface`
- `PayoutExecutionAdapterInterface`
- `ProviderWebhookVerifierInterface`

Then implement provider-specific adapters behind these interfaces.

### 2. Command + Queue Driven Execution
- Final approval emits `payment.execution.requested`.
- Job queue attempts provider call with idempotency key.
- Store result in execution log table.
- Update request/payment state machine.

### 3. Webhook Reconciliation
- Signed webhook endpoint per provider.
- Map provider event IDs to internal execution records.
- Update status: `queued -> processing -> settled | failed | reversed`.

### 4. Operations/Failure Center
- Retry failed executions
- View dead-letter failures
- Manual reconcile tools for edge cases
- Alerting hooks for repeated failures

---

## Security and Compliance Controls
- Tenant-level enablement only by platform authorized roles.
- Mandatory verification checklist before enabling execution:
  - tenant agreement
  - provider onboarding/KYC
  - funding/account setup
- Encrypted secrets and signed webhook verification.
- Immutable execution audit timeline.

---

## Request Workflow Impact

### Decision-only
`approved` is terminal for money movement responsibility in Flowdesk.

### Execution-enabled
`approved` triggers execution phase:
- `approved_for_execution`
- `execution_queued`
- `execution_processing`
- `settled` or `failed` or `reversed`

Thread/timeline must show both approval events and execution events clearly.

---

## Rollout Plan (Phased)

### Phase 1: Flags + Policy + UI
- Add tenant mode flags in Platform Tenant Management.
- Add execution policy controls UI.
- Keep execution disabled by default.

### Phase 2: Subscription Auto-Billing
- Implement automatic subscription billing adapter + webhooks.
- Keep payout execution still disabled.

### Phase 3: Payout Execution for Approved Requests
- Enable payout adapter.
- Wire request terminal approval to execution queue.
- Add payout state timeline + retry center.

### Phase 4: Hardening
- Load tests for queue bursts
- Replay/idempotency tests
- Failure drills and runbooks

---

## Acceptance Criteria

### Decision-only mode
- No external payout/API execution occurs.
- Audit and status remain complete and accurate.

### Execution-enabled mode
- Approved request can execute payment with provider confirmation.
- Every execution has idempotency and webhook verification.
- Failed runs are recoverable from operations center.

---

## Recommended Next Build Order
1. Tenant flags + execution policy schema
2. Provider adapter interfaces (no provider lock-in)
3. Auto subscription billing first
4. Request payout execution second
5. Retry/reconciliation hardening


---

## 7-Phase Implementation Plan

### Phase 1: Tenant Mode Foundation
1. Add tenant mode fields: `payment_execution_mode`, `execution_provider`, `execution_enabled_at`, `execution_enabled_by`.
2. Add execution policy fields per tenant: max transaction, daily cap, monthly cap, checker threshold, allowed channels.
3. Add platform UI in Tenant Details: mode selector + policy form.
4. Add guardrails: mode cannot be enabled without required platform checks.
5. Add audit events for every mode/policy change.
6. Add feature gating service so app behavior branches cleanly by mode.

### Phase 2: Adapter Contracts (Provider-Agnostic)
1. Define interfaces:
   - `SubscriptionBillingAdapterInterface`
   - `PayoutExecutionAdapterInterface`
   - `ProviderWebhookVerifierInterface`
2. Build internal adapter registry/factory by `execution_provider`.
3. Create `null` adapter for decision-only tenants.
4. Add standardized DTOs for request, response, error, retry metadata.
5. Add test doubles/mocks for adapters in feature tests.


#### Phase 2 Status: Implemented (March 1, 2026)
1. Contracts implemented under:
   - `app/Services/Execution/Contracts/*`
2. Standard DTO layer implemented under:
   - `app/Services/Execution/DTO/*`
3. Null adapters implemented for safe decision-only fallback:
   - `NullSubscriptionBillingAdapter`
   - `NullPayoutExecutionAdapter`
   - `NullProviderWebhookVerifier`
4. Provider-agnostic resolution implemented:
   - `ExecutionAdapterRegistry`
   - `TenantExecutionAdapterFactory`
5. Adapter config map implemented:
   - `config/execution.php`
6. Container wiring implemented:
   - `AppServiceProvider` binds registry and factory singletons.
7. Unit tests implemented and passing:
   - `tests/Unit/Execution/ExecutionAdapterRegistryTest.php`

#### Phase 2 Usage (Current)
1. `Decision-only` tenants are forced to null adapters even when `execution_provider` is set.
2. `Execution-enabled` tenants resolve adapters from `execution_provider`.
3. If provider is unknown or missing, registry falls back to configured fallback provider (`null` by default).
4. Real providers can be added later by:
   - Implementing the three contracts,
   - Registering adapter classes in `config/execution.php`,
   - Reusing existing factory/orchestration entry points.
### Phase 3: Subscription Auto-Billing
1. Implement auto-billing orchestration service.
2. Trigger billing jobs by cadence and coverage windows.
3. Persist billing attempts, provider refs, and outcomes.
4. Add webhook endpoint for billing settlement/failure sync.
5. Reconcile billing ledger + tenant subscription status (`current/grace/overdue/suspended`).
6. Add retries + dead-letter handling for failed billing jobs.


#### Phase 3 Skeleton Status: Implemented (March 1, 2026)
1. Added orchestration service:
   - `SubscriptionAutoBillingOrchestrator`
   - Queues one monthly billing attempt per eligible execution-enabled tenant subscription.
2. Added billing attempt processor + queue job:
   - `SubscriptionBillingAttemptProcessor`
   - `RunSubscriptionBillingAttemptJob`
3. Added webhook reconciliation pipeline:
   - `ExecutionWebhookController`
   - `SubscriptionBillingWebhookReconciliationService`
4. Added persistence tables:
   - `tenant_subscription_billing_attempts`
   - `execution_webhook_events`
5. Added manual provider webhook verifier skeleton:
   - `ManualOpsWebhookVerifier`
6. Added console entry points:
   - `tenants:billing:auto-charge`
   - `tenants:billing:process-queued`
7. Added test coverage for queue/process/webhook flows:
   - `SubscriptionAutoBillingPhaseThreeTest`

#### Phase 3 Usage (Current Skeleton)
1. Platform sets tenant to `execution_enabled` and provider key.
2. Scheduler/ops runs `tenants:billing:auto-charge` to queue cycle attempts.
3. Queue worker (or `tenants:billing:process-queued`) processes queued attempts.
4. Adapter result marks attempt as:
   - `webhook_pending` (for async provider confirmation),
   - or terminal statuses (`settled`, `failed`, `skipped`, `reversed`).
5. Provider webhook calls `POST /webhooks/execution/{provider}`.
6. Webhook verifier validates payload/signature and reconciles attempt status.
7. On settled webhook, system posts a debit ledger entry and re-evaluates tenant billing status.
### Phase 4: Request Payout Execution
1. On final approval, transition request to `approved_for_execution`.
2. Queue payout job with idempotency key.
3. Call payout adapter and persist execution log.
4. Add webhook reconciliation for payout events.
5. Update request lifecycle: `execution_queued`, `execution_processing`, `settled/failed/reversed`.
6. Reflect execution events in request timeline/thread.


#### Phase 4 Status: Implemented (March 1, 2026)
1. Request approvals are now scope-aware (`request` and `payment_authorization`) using `request_approvals.scope`.
2. Request submission explicitly initializes `approval_scope=request` and writes scoped approval rows.
3. Final request approval in execution-enabled tenants now transitions to payment-authorization scope instead of immediately finalizing.
4. Final payment-authorization approval now transitions request to execution lifecycle and queues payout attempt orchestration.
5. Added payout execution persistence:
   - `request_payout_execution_attempts`
   - webhook linkage via `execution_webhook_events.request_payout_execution_attempt_id`
6. Webhook reconciliation now supports fallback from billing reconciliation to payout reconciliation on the same endpoint.
7. Request UI now supports execution statuses (`approved_for_execution`, `execution_queued`, `execution_processing`, `settled`, `failed`, `reversed`) and timeline scope labels.
8. Added Phase 4 tests:
   - `tests/Feature/Execution/RequestPayoutExecutionPhaseFourTest.php`

#### Phase 4 Usage (Current)
1. Tenant remains `Decision-only`: final request approval ends at `approved` (no payout execution).
2. Tenant is `Execution-enabled` with payment-authorization workflow:
   - request scope approvals complete,
   - request enters payment-authorization scope,
   - final payment authorization queues payout execution.
3. Provider callbacks still use `POST /webhooks/execution/{provider}`;
   billing reconciliation runs first, then payout reconciliation fallback if payload matches payout attempts.
4. With null/manual adapters, payout can resolve to `approved_for_execution`/non-terminal execution states depending on adapter response and queue processing mode.
### Phase 5: Operations Center
1. Add platform ops screen for execution failures/stuck jobs.
2. Add resend/retry controls with reason capture.
3. Add filters: provider, tenant, status, age.
4. Add dead-letter visibility and manual reconcile actions.
5. Add correlation IDs across request, billing, and execution logs.
6. Add alert hooks for repeated failures.

### Phase 6: Security + Compliance Hardening
1. Encrypt provider credentials/secrets at rest.
2. Enforce signed webhook verification.
3. Add replay protection (nonce/timestamp checks).
4. Tighten role permissions for mode enablement and retries.
5. Add immutable audit trail for execution-critical actions.
6. Add rate limits and abuse guards on sensitive endpoints.

### Phase 7: QA + Release Readiness
1. End-to-end tests per mode:
   - Decision-only: no external execution.
   - Execution-enabled: full payout lifecycle.
2. Failure-path tests: timeout, duplicate webhook, partial settlement.
3. Load tests on queue/webhook throughput.
4. UAT checklist for platform ops and tenant admins.
5. Rollback plan and kill-switch for execution mode.
6. Production runbook + monitoring dashboard sign-off.

---

## Architecture Clarification (Approved)

The platform model is locked to the following governance rules:

1. Default mode for every tenant is `Decision-only`.
2. `Execution-enabled` is configured only from central platform Tenant Management, not from tenant-side settings.
3. Request approval and money movement approval are separate control layers:
   - Layer A: request approval workflow (existing request chain)
   - Layer B: payment authorization workflow (runs after request is finally approved and before payout execution)
4. When `Execution-enabled` is active, final payout/payment must pass payment-authorization policy steps tied to hierarchy, roles, and designations.
5. Execution policy applies only after request reaches final approval, and does not bypass standard request approvals.

This means Flowdesk remains safe by default (`Decision-only`) while allowing strict, policy-driven execution controls per tenant when enabled by platform operators.


---

## Phase 1.5: Payment-Authorization Policy Skeleton (Implemented)

### What is now implemented
1. Approval Workflows now supports two policy scopes:
   - `Request Approval`
   - `Payment Authorization`
2. Owners can switch scope on the same workflow page and manage each scope independently.
3. Preset workflow creation is scope-aware:
   - Request preset: `Direct Manager -> Finance`
   - Payment authorization preset: `Finance -> Admin (Owner)`
4. Duplicate cleanup, default-setting, creation, and step management are all scoped per policy type.
5. Platform execution guardrail now enforces this rule:
   - A tenant cannot move to `Execution-enabled` mode unless it has an active default `Payment Authorization` workflow.
6. Added skeleton resolver service:
   - `PaymentAuthorizationWorkflowResolver`
   - Resolves default payment-authorization workflow and amount-applicable steps for future execution engine integration.

### Current operational flow (approved)
1. Request goes through request-approval workflow.
2. If tenant is `Decision-only`, flow ends at approval decision (no payout execution).
3. If tenant is `Execution-enabled`, system requires payment-authorization workflow before any payout execution stage.
4. Payment execution integration remains phase-gated and comes after this policy layer.

### Where this is configured
1. Organization owner side:
   - `Approval Workflows` page
   - Use scope switch to configure `Request Approval` or `Payment Authorization` chains.
2. Platform operator side:
   - `Platform -> Tenants -> Update Tenant`
   - `Execution Mode` can only be enabled when payment-authorization default policy exists.

### Why this matters
This preserves separation of concerns:
- Request approval decides business intent.
- Payment authorization decides release-of-funds authority.
- Execution (provider calls) remains a later controlled phase.
