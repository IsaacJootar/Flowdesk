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

