# Flowdesk Final Execution Checklist

## Objective
Ship Flowdesk to production-ready quality for small and large organizations with clear controls, stable operations, and release governance.

## Current Scope Baseline (Already Built)
- [x] Requests and approvals module with workflow routing, inbox, reports, escalation timers, and communication logging
- [x] Expense module with budget guardrails, duplicate detection, controls, and audit timelines
- [x] Vendor module with directory, invoices, payments, statements, and vendor reporting
- [x] Asset module with categories, register, assignment, returns, history, and reminders
- [x] Settings split into focused pages (company, team, departments, workflows, controls, communications)
- [x] Role-based navigation foundation and hierarchy-aware approvals

---

## Phase 0: Tenant and Organization Management (Prerequisite)
### 0.1 Tenant Core
- [x] Tenant registry (create/update/status/lifecycle)
- [x] Tenant lifecycle controls (suspend, deactivate, archive)
- [x] Tenant-level audit trail for all admin actions

### 0.2 Plan and Entitlements
- [x] Plan assignment per tenant
- [x] Tenant feature entitlement overrides
- [x] Seat and usage governance

### 0.3 Billing Operations (Manual + Offline Support)
- [x] Manual payment capture for cash/offline transfer tenants
- [x] Billing ledger with allocations and balances
- [x] Billing status derivation (`current`, `grace`, `overdue`, `suspended`)
- [x] Reconciliation and exception queue for unapplied/mismatched payments

### Exit Criteria
- [x] Central admin can fully govern tenant lifecycle and plan/entitlements
- [x] Offline billing is auditable and reliable for subscription state decisions
- [x] Tenant state can be used as source-of-truth for plan gating

Reference: `FLOWDESK_TENANT_ORGANIZATION_MANAGEMENT_PLAN.md`

---

## Money Movement Operating Model
There are 2 models, and orgs choose:

### Model 1: Decision-only (Current Flowdesk model)
- [x] Flowdesk handles request intake, approvals, controls, and audit trail
- [x] Organization executes money movement in existing bank/ERP/payment rails
- [x] Flowdesk records status/history but does not initiate external transfer by default

### Model 2: Execution (Deeper integration, planned for later)
- [ ] Flowdesk can trigger payouts/transfers through approved provider APIs
- [ ] Webhook-confirmed settlement status updates into Flowdesk timelines
- [ ] Optional per organization/tenant, contract and compliance dependent

---

## Phase 1: AI Track (Product Intelligence)
### 1.1 Document Intelligence
- [ ] OCR ingestion pipeline for receipts/invoices/supporting files
- [ ] Field extraction for amount/date/vendor/reference
- [ ] Confidence score + manual correction UX
- [ ] Save corrected values for model feedback loop

### 1.2 Classification and Insights
- [ ] Auto-categorize spend/request items
- [ ] Suggest likely vendor/category from prior patterns
- [ ] Flag anomalies (amount outliers, duplicate-like variations)
- [ ] Forecast trend cards (monthly spend, approval delays, vendor exposure)

### 1.3 AI Assistant Surface
- [ ] Assistant endpoint/service wrapper
- [ ] Prompt-safe, permission-scoped query layer
- [ ] In-app assistant panel for summaries and recommendations
- [ ] Audit log of assistant actions and data access

### Exit Criteria
- [ ] AI suggestions are visible, explainable, and overridable
- [ ] AI never bypasses policy controls
- [ ] Core AI flows covered by automated tests

---

## Phase 2: Fintech Track (Money Movement Integrations)
### 2.1 Connectivity
- [ ] Integrate account/wallet feed adapters
- [ ] Transaction import scheduler + webhook fallback
- [ ] Idempotent ingest and duplicate suppression

### 2.2 Reconciliation
- [ ] Match expenses/payments to external transactions
- [ ] Manual reconcile UI for unresolved items
- [ ] Reconcile status in reports and audit timeline

### 2.3 Failure Handling
- [ ] Retry queue and dead-letter visibility
- [ ] Integration status dashboard (healthy/degraded/down)
- [ ] Alerting hooks for critical sync failures

### Exit Criteria
- [ ] Daily transaction sync reliability target met
- [ ] Reconciliation reports trustworthy for finance review
- [ ] Integration failures are observable and recoverable

---

## Phase 3: Plan Gating and Billing
### 3.1 Feature Access Controls
- [x] Add tenant-level feature flags
- [x] Map features/modules to plan tiers (Pilot/Growth/Business/Enterprise)
- [x] Enforce both UI and server-side authorization gates

### 3.2 Subscription/Billing
- [ ] Subscription model and plan lifecycle states
- [ ] Billing provider integration
- [ ] Upgrade/downgrade flow with guardrails
- [ ] Grace period and suspension behavior

### Exit Criteria
- [ ] Module access changes immediately with plan changes
- [ ] No unauthorized API access through direct routes
- [ ] Billing events and entitlement states are consistent

---

## Phase 4: Hardening and Operations
### 4.1 Security and Permissions
- [ ] Final pass on authorization policies by module action
- [ ] Rate limits on sensitive endpoints
- [ ] Validation hardening on all create/update actions
- [ ] Tenant boundary checks in all data queries

### 4.2 Performance and Reliability
- [ ] Pagination everywhere high-volume data exists
- [ ] Query optimization for reports and inbox pages
- [ ] Queue throughput and retry tuning
- [ ] Caching strategy for expensive dashboards

### 4.3 Observability and Support
- [ ] Structured logging with correlation IDs
- [ ] Error tracking integration
- [ ] Admin diagnostics for queued jobs + delivery logs
- [ ] Backup/restore playbook

### Exit Criteria
- [ ] No critical auth/data-leak findings
- [ ] P95 page and report response targets met
- [ ] On-call and recovery runbooks tested

---

## Phase 5: QA, UAT, and Release
### 5.1 Automated Test Coverage
- [ ] End-to-end tests for request lifecycle
- [ ] End-to-end tests for expense/vendor/asset lifecycles
- [ ] Regression suite for permissions and controls
- [ ] Seed-based smoke tests for multi-role scenarios

### 5.2 UAT and Documentation
- [ ] UAT scripts for owner/finance/manager/staff/auditor
- [ ] Admin setup guide (hierarchy, controls, workflows)
- [ ] Operator guide (requests, expenses, vendors, assets)
- [ ] Incident response and escalation guide

### 5.3 Go-Live
- [ ] Production env validation checklist
- [ ] Data migration and rollback plan
- [ ] Final release candidate sign-off
- [ ] Post-launch monitoring window and triage ownership

### Exit Criteria
- [ ] UAT pass signed by stakeholders
- [ ] Release candidate approved with rollback readiness
- [ ] Monitoring and support coverage in place

---

## Final Sign-Off Gate
- [ ] Security sign-off
- [ ] Finance/process owner sign-off
- [ ] Product sign-off
- [ ] Engineering release sign-off

## Suggested Execution Order
1. Tenant and Organization Management
2. Plan Gating and Billing
3. Hardening and Operations
4. AI Track
5. Fintech Track
6. QA/UAT/Go-Live

## File Ownership
- Product execution tracker: `FLOWDESK_FINAL_EXECUTION_CHECKLIST.md`

## Latest Progress Update (2026-02-27)
- [x] Platform dashboard route finalized (`/platform`)
- [x] Internal platform org slug filtering added for tenant counts/lists
- [x] Tenant first-login provisioning implemented (auto + manual button)
- [x] Tenant entitlement enforcement now active on both sidebar visibility and route access
- [x] Edge-case tests added for entitlement gating:
  `tests/Feature/Settings/TenantModuleEntitlementTest.php`
- [x] Tenant details page added:
  `platform/tenants/{company}` with ledger, allocations queue, plan timeline, usage, and audit events
- [x] Billing/audit entities added:
  `tenant_billing_ledger_entries`, `tenant_billing_allocations`, `tenant_plan_change_histories`, `tenant_usage_counters`, `tenant_audit_events`
- [x] Tenant billing ops regression tests added:
  `tests/Feature/Settings/TenantBillingOpsTest.php`
- [x] Automated tenant billing status engine wired:
  - service: `app/Services/TenantBillingAutomationService.php`
  - scheduler/command: `tenants:billing:automate` in `routes/console.php`
  - live hooks: tenant page load, tenant save, payment save
- [x] Seat governance enforced at team actions:
  - blocks user create/activation when tenant seat cap is reached
  - files: `CreateCompanyUser`, `UpdateCompanyUserAssignment`, `TenantSeatGovernanceService`
- [x] Plan matrix defaults added:
  - config: `config/tenant_plans.php`
  - service: `app/Services/TenantPlanDefaultsService.php`
  - tenant modal action: `Apply Plan Defaults`
- [x] Approval workflow policy split implemented for two-layer controls:
  - `request` scope and `payment_authorization` scope in `ApprovalWorkflowsPage`
  - scope-aware presets and duplicate cleanup
  - file: `app/Livewire/Organization/ApprovalWorkflowsPage.php`
- [x] Execution-mode guardrail hardened to require default payment-authorization policy before enabling:
  - service: `app/Services/TenantExecutionModeService.php`
  - skeleton resolver: `app/Services/PaymentAuthorizationWorkflowResolver.php`
