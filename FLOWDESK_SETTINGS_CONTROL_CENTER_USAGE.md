# Flowdesk Settings Control Center Usage

Last updated: 2026-03-07

## Purpose

Settings Control Center (`/settings`) is the primary tenant settings workspace for owners.
It centralizes configuration navigation so teams stop jumping across many sidebar links.

## Tabs

1. Organization
- Company setup, departments, team, approval workflows.

2. Requests & Communications
- Request configuration, approval timing controls, communications channel controls.

3. Module Controls
- Expense, vendor, asset, procurement, treasury, and Payments Rails Integration controls.

4. Security
- Profile and credential management.

## Payments Rails Integration (owner action map)
Route: `/settings/payments-rails`

1. `Connect`
- Saves selected provider and marks tenant rail as connected.

2. `Test Connection`
- Runs basic provider readiness check and stores pass/fail + message + test time.

3. `Sync Now`
- Records a manual sync timestamp for tenant operations tracking.

4. `Pause/Resume`
- Toggles tenant rail state between paused and connected.

Audit trail:
- Tenant page: `Recent Payments Rail Actions` (10 per page).
- Platform page: `/platform/tenants/{company}/billing` -> `Tenant Audit Events`.

## State labels

- `Enabled`: control is available in current tenant plan.
- `Disabled by plan`: module is not entitled for this tenant.

## Rules

- Only tenant owner can open Settings Control Center.
- All setting changes remain tenant-scoped to the owner's organization.
- Detailed pages still exist as drill-down actions from this center.
