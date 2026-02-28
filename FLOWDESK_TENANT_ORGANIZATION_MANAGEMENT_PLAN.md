# Flowdesk Tenant and Organization Management Plan

## Purpose
Provide a central control module for creating, governing, and billing tenant organizations before plan gating and subscription enforcement.

## Why This Module Comes First
- Plan gating depends on tenant-level entitlement records.
- Subscription enforcement depends on tenant lifecycle state.
- Operations need a central place to suspend/deactivate/reactivate organizations.

---

## Core Module Scope

### 1. Tenant Registry
- Create organization tenant
- Edit organization profile and metadata
- Unique tenant code/slug
- Status: `active`, `suspended`, `inactive`, `archived`

### 2. Tenant Lifecycle Controls
- Suspend / unsuspend tenant
- Deactivate / reactivate tenant
- Archive tenant
- Mandatory action reason + audit log

### 3. Subscription and Plan Assignment
- Assign plan: `Pilot`, `Growth`, `Business`, `Enterprise`
- Track start/end dates
- Grace period fields
- Plan change history

### 4. Feature Entitlements
- Per-tenant module toggles
- Plan-default entitlements + explicit overrides
- Optional beta feature flags

### 5. Seat and Usage Governance
- User seat caps by tenant
- Module usage limits
- Current usage vs quota
- Threshold warnings

### 6. Tenant Security Operations
- Tenant admin assignments
- Force password reset at tenant scope
- Emergency tenant lock
- Revoke active sessions/tokens at tenant scope

---

## Billing Operations (Admin Side)

### 7. Manual/Offline Billing (Required)
Support organizations that pay by cash, offline transfer, or other non-automated channels.

- Record manual payment entries:
  - amount, currency, method, received date, reference, note
- Attach payment evidence (receipt/proof file)
- Allocate payment to billing period(s)
- Support partial payment and carry-forward balances
- Mark internal invoice as paid manually

### 8. Billing Ledger
- Per-tenant ledger with:
  - debits, credits, adjustments, running balance
- Billing status calculation:
  - `current`, `grace`, `overdue`, `suspended`
- Manual override with mandatory reason

### 9. Controls and Audit
- Restricted billing roles for manual entries
- Optional maker-checker approval for adjustments/write-offs
- Full audit trail for all billing actions
- Before/after value diffs for edits and reversals

### 10. Reconciliation
- Expected subscription schedule vs recorded payments
- Unapplied payment queue
- Mismatch/exception alerts

---

## UI/Navigation

### Admin Navigation
- `Tenants` (new primary admin module)

### Pages
- Tenants List
  - search, filters, status, plan, pagination
  - bulk actions: suspend/reactivate/plan change
- Tenant Details
  - tabs: Profile, Lifecycle, Plan, Entitlements, Usage, Billing, Audit
- Billing Ledger
  - manual payments, allocations, invoices, adjustments, reconciliation

---

## Permissions Model
- `platform_owner`: full tenant control
- `platform_billing_admin`: billing + plan assignment
- `platform_ops_admin`: lifecycle + entitlements
- Read-only admin role for reporting/audit access

All sensitive actions require:
- explicit permission check
- actor identity capture
- reason/comment where applicable

---

## Data and API Requirements

### Key Entities
- `tenants`
- `tenant_status_history`
- `tenant_subscriptions`
- `tenant_feature_entitlements`
- `tenant_usage_counters`
- `tenant_billing_ledger`
- `tenant_manual_payments`
- `tenant_billing_allocations`
- `tenant_audit_events`

### API Surface (Internal Admin)
- Tenant CRUD + lifecycle endpoints
- Plan assignment and entitlement endpoints
- Manual billing endpoints
- Ledger/reconciliation read endpoints

---

## Delivery Sequence
1. Tenant registry + lifecycle + audit
2. Plan assignment + entitlements
3. Manual billing + ledger + reconciliation
4. Usage/seat governance and alerts
5. Integration hooks for future automated billing providers

---

## Done Criteria
- Tenant can be created/updated/suspended/deactivated from central admin
- Plan and feature entitlements are tenant-scoped and enforceable
- Offline/manual payments can be recorded and audited end-to-end
- Billing status is reliable for gating access and lifecycle actions
- All high-risk actions are permissioned and auditable

---

## Current Implementation Status (Phase 1 Slice)
- Added platform route: `platform/tenants` (outside tenant company context middleware)
- Added dedicated platform dashboard route: `platform/`
- Added platform-only access rule: owner account with no tenant company assignment
- Added tenant lifecycle fields on companies:
  - `lifecycle_status`, `status_reason`, `status_updated_at`
- Added subscription table:
  - `tenant_subscriptions`
- Added entitlement table:
  - `tenant_feature_entitlements`
- Added manual payment table:
  - `tenant_manual_payments`
- Added central UI page for:
  - tenant list with search/filters/pagination
  - create/edit tenant
  - lifecycle actions
  - plan and entitlement updates
  - manual payment recording
- Added tenant login provisioning:
  - auto-provisions first tenant owner login on create when missing
  - manual `Provision Login` action for legacy tenants with zero users
- Added internal-platform org filtering from tenant metrics/list via config:
  - `config/platform.php`
- Added entitlement enforcement layer:
  - sidebar link filtering by tenant entitlements
  - route-level module gating middleware (`module.enabled`)
- Added automated regression coverage for entitlement gating:
  - `tests/Feature/Settings/TenantModuleEntitlementTest.php`
- Added dedicated tenant details operations page:
  - route: `platform/tenants/{company}`
  - billing ledger
  - reconciliation queue (allocation status filters)
  - plan change timeline
  - usage snapshots with quota warning levels
  - tenant audit events timeline
- Added tenant billing/audit data entities:
  - `tenant_billing_ledger_entries`
  - `tenant_billing_allocations`
  - `tenant_plan_change_histories`
  - `tenant_usage_counters`
  - `tenant_audit_events`
- Wired tenant operations to audit + ledger:
  - manual payment now writes ledger + allocation + audit
  - plan/status changes now write history + audit
  - lifecycle actions write tenant audit events
  - usage snapshots captured after key admin mutations
- Added automated regression coverage for tenant billing ops:
  - `tests/Feature/Settings/TenantBillingOpsTest.php`
- Added automated billing lifecycle derivation:
  - `current`, `grace`, `overdue`, `suspended` from coverage + grace policy
  - command/scheduler: `tenants:billing:automate`
  - auto-run hooks on tenant load, tenant save, and manual payment save
- Added plan policy matrix defaults:
  - config: `config/tenant_plans.php`
  - service: `TenantPlanDefaultsService`
  - tenant modal action: `Apply Plan Defaults`
- Added seat governance enforcement:
  - service: `TenantSeatGovernanceService`
  - enforced in user create and user re-activation paths
  - usage snapshots auto-captured after team changes

### Pending in Next Slice
- tenant billing provider adapter layer (optional, if external automation is enabled)
