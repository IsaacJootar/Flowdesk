# FLOWDESK_PAYMENTS_RAILS_SCOPE.md

Last updated: 2026-03-07

## Purpose
This document prevents duplication between existing platform operations tooling and the Payments Rails Integration scope.

## 1) What already exists (do not duplicate)
These are already implemented and remain the control/governance layer:
1. Platform tenant execution mode/policy pages.
2. Platform execution operations center and incident history.
3. Tenant execution, procurement, treasury, and request lifecycle workspaces.

Rule: do not rebuild payout/procurement/treasury desks under fintech scope.

## 2) What Payments Rails Integration does
This scope is for external provider rails connectivity and synchronization readiness:
1. Provider connection lifecycle (connect, verify, pause/resume, health).
2. Rail diagnostics sync for provider status checks.
3. Webhook signature readiness validation before external provider activation.

Rule: platform decides/governs, rails integration executes/syncs external state.

## 3) Tenant vs Platform ownership
## Tenant-side
1. Business-friendly status and readiness.
2. No raw API/webhook payload handling.
3. Route: `/settings/payments-rails`.

## Platform-side
1. Technical diagnostics and incident triage.
2. Webhook verification failures and provider outage operations.
3. Cross-tenant reliability controls.

## 4) Current tenant actions (implemented)
These actions are on `/settings/payments-rails` and are tenant-scoped by `company_id`.

1. `Connect`
- Applies staged rollout policy (`manual` / `sandbox` / `live`).
- Runs provider diagnostics probe (sandbox-aware for pilot tenants).
- Validates webhook signature readiness before external provider is activated.
- Success audit: `tenant.payments_rails.connected`.
- Failure audit: `tenant.payments_rails.connect_failed`.

2. `Test Connection`
- Runs provider diagnostics probe with current rail mode (`sandbox` or `live`).
- Persists pass/fail status, message, and timestamp.
- Success audit: `tenant.payments_rails.connection_tested`.
- Failure audit: `tenant.payments_rails.connection_test_failed`.

3. `Sync Now`
- Runs provider sync probe and updates health metadata.
- Requires connected rail state.
- Success audit: `tenant.payments_rails.sync_requested`.
- Failure audit: `tenant.payments_rails.sync_failed`.

4. `Pause/Resume`
- `Pause` marks rail paused and updates health state.
- `Resume` runs readiness check before reconnecting.
- Success audits: `tenant.payments_rails.paused`, `tenant.payments_rails.resumed`.
- Resume failure audit: `tenant.payments_rails.resume_failed`.

## 5) Health + visibility
1. Tenant side visibility:
- `/settings/payments-rails` cards now include:
  - Connection status
  - Rail health (`Healthy`, `Degraded`, `Action needed`, `Paused`)
  - Webhook signature readiness (`Ready`, `Missing setup`, `Optional`)
  - Last test and last sync timestamps
- `Recent Payments Rail Actions` table is paged (10 per page).

2. Platform side visibility:
- `/platform/tenants/{company}/billing` -> `Tenant Audit Events` includes all `tenant.payments_rails.*` actions (success and failure).

3. Incident timeline scope note:
- `/platform/operations/incident-history` remains execution-incident focused and does not currently index `tenant.payments_rails.*` actions.

## 6) UX guardrails
1. Use plain labels: Connected, Action needed, Last sync.
2. Keep diagnostics concise and actionable.
3. Keep sensitive provider payload internals out of tenant-facing screens.

## 7) Staged rollout policy (implemented)
1. Default provider for new tenant setup is `manual_ops`.
2. External providers (`paystack`, `flutterwave`, etc.) are blocked unless tenant is in pilot or go-live allow-list.
3. Pilot tenants connect external providers in `Sandbox` stage.
4. Go-live approved tenants connect external providers in `Live` stage.
5. Tenant execution mode save also enforces the same staged rollout guardrails.
6. Rollout config keys:
- `execution.rails_rollout.default_provider`
- `execution.rails_rollout.pilot_company_slugs`
- `execution.rails_rollout.go_live_company_slugs`
- `execution.rails_rollout.allow_external_provider_without_pilot`

## 8) Webhook signature validation
1. Runtime webhook verifiers now validate against configured live + sandbox secrets/hashes.
2. `paystack`: verifies `x-paystack-signature` using configured signing secrets.
3. `flutterwave`: verifies `verif-hash` (preferred) or HMAC signature fallback with signing keys.
4. Route remains `POST /webhooks/execution/{provider}` with provider-specific verifier resolution.