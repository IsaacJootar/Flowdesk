# FLOWDESK_PAYMENTS_RAILS_SCOPE.md

Last updated: 2026-03-07

## Purpose
This document prevents duplication between existing platform operations tooling and the future Payments Rails Integration scope.

## 1) What already exists (do not duplicate)
These are already implemented and remain the control/governance layer:
1. Platform tenant execution mode/policy pages.
2. Platform execution operations center and incident history.
3. Tenant execution, procurement, treasury, and request lifecycle workspaces.

Rule: do not rebuild payout/procurement/treasury desks under fintech scope.

## 2) What Payments Rails Integration should do
This scope is only for external provider rails connectivity and synchronization:
1. Provider connection lifecycle (connect, verify, pause/resume, health).
2. Rail event synchronization (settled, failed, reversed, fees).
3. Settlement feed ingestion for treasury reconciliation support.

Rule: platform decides/governs, rails integration executes/syncs external state.

## 3) Tenant vs Platform ownership
## Tenant-side
1. Business-friendly status and readiness.
2. No raw API/webhook payload handling.
3. Current foundation page: `/settings/payments-rails`.

## Platform-side
1. Technical diagnostics and incident triage.
2. Webhook verification failures and provider outage operations.
3. Cross-tenant reliability controls.

## 4) Current tenant actions (implemented)
These actions are on `/settings/payments-rails` and are tenant-scoped by `company_id`.

1. `Connect`
- Saves selected provider for the tenant.
- Sets status to `connected`.
- Writes audit event: `tenant.payments_rails.connected`.

2. `Test Connection`
- Runs basic readiness check for selected provider.
- Stores pass/fail result, message, and tested timestamp.
- Writes audit event: `tenant.payments_rails.connection_tested`.

3. `Sync Now`
- Records a manual sync timestamp for operations tracking.
- Requires connected state.
- Writes audit event: `tenant.payments_rails.sync_requested`.

4. `Pause/Resume`
- Toggles rail status between `paused` and `connected`.
- Writes audit events: `tenant.payments_rails.paused` and `tenant.payments_rails.resumed`.

## 5) Visibility and alignment
1. Tenant side visibility:
- `/settings/payments-rails` -> `Recent Payments Rail Actions` table (paged, 10 per page).

2. Platform side visibility:
- `/platform/tenants/{company}/billing` -> `Tenant Audit Events` includes all `tenant.payments_rails.*` actions.

3. Incident timeline scope note:
- `/platform/operations/incident-history` is execution-incident focused and does not currently index `tenant.payments_rails.*` actions.
- This is intentional to keep incident timeline focused on execution recoveries/alerts.

## 6) UX guardrails
1. Use plain labels: Connected, Action needed, Last sync.
2. Hide raw technical payload details from tenant-facing screens.
3. Keep all sensitive provider diagnostics platform-only.

## 7) Delivery order
1. Keep existing operations desks as source of truth.
2. Build provider onboarding + sync events behind the new Payments Rails settings shell.
3. Integrate synced events into existing treasury/incident views.
