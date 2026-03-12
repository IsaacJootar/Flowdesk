# Flowdesk Final Execution Checklist

## Objective
Ship Flowdesk to production-ready quality for small and large organizations with clear controls, stable operations, and release governance.

## Implemented Modules Snapshot (2026-03-08)
### Tenant Application Modules
- [x] Dashboard
- [x] Execution (Health, Payout Ready Queue, Help)
- [x] Procurement (Release Desk, Orders, Receipts, Match Exceptions)
- [x] Treasury (Reconciliation Desk, Exceptions, Payment Runs, Cash Position)
- [x] Requests and Approvals (Lifecycle Desk, Communications Recovery, Reports)
- [x] Expenses
- [x] Vendors (Command Center, Registry, Details, Reports)
- [x] Budgets
- [x] Assets (Register and Reports)
- [x] Reports Center
- [x] Organization (Admin Desk, Departments, Team, Approval Workflows)
- [x] Tenant Settings (Control Center + module control pages)

### Platform Modules
- [x] Tenant and Organization Management
- [x] Platform Users
- [x] Tenant Execution Mode and Execution Policy
- [x] Platform Operations Hub and Execution Operations
- [x] Execution Test Checklist
- [x] Incident History
- [x] Pilot Rollout KPI Capture

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
- [x] Flowdesk can trigger payouts/transfers through approved provider APIs
- [x] Webhook-confirmed settlement status updates into Flowdesk timelines
- [x] Optional per organization/tenant, contract and compliance dependent

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

Progress note (2026-03-09):
- Requests module now includes tenant-gated **Flow Agents** advisory panels in draft/view modals (`app/Livewire/Requests/RequestsPage.php`, `resources/views/livewire/requests/requests-page.blade.php`).
- Flow Agents now includes user-triggered workflow actions in Requests while keeping user as first actor (no autonomous execution).
- Advisory engine implemented in `app/Services/AI/RequestFlowAgentService.php` with feature tests in `tests/Feature/Requests/RequestFlowAgentsTest.php`.
- Expenses module now includes **Receipt Agent** (`app/Services/AI/ExpenseReceiptIntelligenceService.php`) with apply-suggestion UX, duplicate preview guard, and explicit create-button diagnostics (`app/Livewire/Expenses/ExpensesPage.php`, `resources/views/livewire/expenses/expenses-page.blade.php`).
- Added feature coverage: `tests/Feature/Expenses/ExpenseReceiptAgentTest.php`.

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
- [x] Match expenses/payments to external transactions
- [x] Manual reconcile UI for unresolved items
- [x] Reconcile status in reports and audit timeline

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
- [x] Subscription model and plan lifecycle states
- [x] Billing provider integration
- [x] Upgrade/downgrade flow with guardrails
- [x] Grace period and suspension behavior

### Exit Criteria
- [x] Module access changes immediately with plan changes
- [x] No unauthorized API access through direct routes
- [x] Billing events and entitlement states are consistent

---

## Phase 4: Hardening and Operations
### 4.1 Security and Permissions
- [ ] Final pass on authorization policies by module action
- [x] Rate limits on sensitive endpoints
- [ ] Validation hardening on all create/update actions
- [ ] Tenant boundary checks in all data queries

### 4.2 Performance and Reliability
- [x] Pagination everywhere high-volume data exists
- [x] Query optimization for reports and inbox pages
- [x] Queue throughput and retry tuning
- [x] Caching strategy for expensive dashboards

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

## Latest Progress Update (2026-03-09)
- [x] Execution Health recent events/summaries expanded to include manual runs, auto-recovery outcomes, and alert delivery outcomes; tenant copy updated for clearer operator guidance.
- [x] Tenant boundary hardening pass completed for platform-vs-tenant route separation (`EnsureCompanyContext`) and explicit `company_id` filters in dashboard/report query paths.
- [x] Validation hardening expanded for Vendors page:
  - `VendorsPage` now normalizes list/detail filter state and operator inputs (`status`, `type`, invoice/statement status, statement date range, reminders, communication queue thresholds, per-page values) to strict allow-lists/ranges.
  - Added Livewire regression coverage: `tests/Feature/Vendors/VendorsPageValidationHardeningTest.php`.
- [x] Validation hardening expanded for Assets + Procurement + Requests workspaces:
  - `AssetsPage` now normalizes search/status/category/assignment/per-page filters to strict allow-lists.
  - `PurchaseOrdersPage` now normalizes search/status/per-page and enforces tenant/order-scoped `exists` validation for goods-receipt line item IDs.
  - `PurchaseOrdersPage` invoice-link action now rejects cross-tenant / wrong-vendor / invalid invoice IDs at page boundary.
  - `PurchaseReceiptsPage` now normalizes search/status/date-range/per-page filters with strict date parsing.
  - `RequestsPage` now normalizes search/status/type/department/scope/date-range/per-page filters before query execution.
  - Added regression coverage:
    - `tests/Feature/Assets/AssetsPageValidationHardeningTest.php`
    - `tests/Feature/Finance/ProcurementPagesValidationHardeningTest.php`
    - `tests/Feature/Requests/RequestsPageValidationHardeningTest.php`
- [x] UI consistency pass completed for Edit/View actions:
  - Added iconized Edit/View action buttons (matching Expenses page pattern) across Budgets, Vendors (registry/details), Requests, Procurement Orders/Receipts, Assets, and Settings control pages.
- [x] Sensitive endpoint throttles wired:
  - `execution-webhooks` for `/webhooks/execution/{provider}`
  - `tenant-downloads` for attachment download endpoints
  - `tenant-exports` for vendor statement export/print endpoints
- [x] Vendor statement endpoint validation tightened (`from`, `to`, `invoice_status`) with strict date format/order and status allow-list checks.
- [x] Authorization matrix hardening pass started for Vendors + Procurement:
  - Added explicit procurement policies (`PurchaseOrder`, `GoodsReceipt`, `InvoiceMatchException`) and policy wiring in `AuthServiceProvider`.
  - Procurement workspaces now rely on policy-based `viewAny` checks instead of duplicated role lists.
  - Procurement order actions (`issue`, `recordReceipt`, `linkInvoice`) now perform explicit policy authorization checks in Livewire handlers.
  - Vendor statement endpoints now have regression coverage for role/policy denial paths.
- [x] Authorization matrix hardening expanded for Treasury + Requests:
  - Added treasury policies (`BankStatement`, `BankAccount`, `PaymentRun`, `ReconciliationException`) and wired policy mapping in `AuthServiceProvider`.
  - Treasury reconciliation, exceptions, payment runs, cash position, and help pages now use policy-based access checks instead of duplicated role arrays.
  - Request lifecycle/help and communications-help entry checks now use `SpendRequest` policy gate (`viewAny`) to enforce active-user scope consistently.
- [x] Authorization matrix hardening expanded for tenant Execution pages:
  - Added `RequestPayoutExecutionAttemptPolicy` and mapped it in `AuthServiceProvider`.
  - `ExecutionHealthPage`, `PayoutReadyQueuePage`, and `ExecutionUsageGuidePage` now use policy abilities instead of duplicated inline role arrays.
  - Manual payout run permission now uses explicit policy ability `queueAny` (owner/finance/manager only), while read access uses `viewAny` (owner/finance/manager/auditor).
  - Added regression coverage for manual-run guardrail: auditors can monitor queue but cannot trigger `runPayoutNow` (`TenantPayoutReadyQueuePageTest`).
- [x] Validation hardening wave completed for Treasury + Requests:
  - Treasury reconciliation import now validates selected bank account via tenant-bound `exists` check (`company_id` + active account).
  - Treasury reconciliation and exception closure now validate `resolutionAction` with explicit allow-list (`resolved`, `waived`).
  - Treasury reconciliation/payment runs/exceptions filters now normalize per-page/search/status/type/stream values to safe allow-lists.
  - Request reports filters now normalize status/type/department/date/per-page and enforce explicit tenant `company_id` filter in base query.
  - Communications Recovery Desk now normalizes tab/filter state and blocks delivery-log tab forcing via tampered component state.
- [x] Validation hardening expanded for Expenses page:
  - Added strict filter normalization in `ExpensesPage` for `status`, `payment_method`, `vendor`, `department`, `dateFrom/dateTo`, and `perPage` to block tampered component state values.
  - Hardened local form validation with tenant-bound `exists` checks for `department_id`, `vendor_id`, and `paid_by_user_id`.
  - Added Livewire regression coverage: `tests/Feature/Expenses/ExpensesPageValidationHardeningTest.php`.
- [x] Validation hardening expanded for Budgets page:
  - Added strict filter normalization in `BudgetsPage` for `department`, `status`, `period type`, and `perPage` to block tampered component state values.
  - Hardened local form validation with tenant-bound `exists` check for `department_id`.
  - Added Livewire regression coverage: `tests/Feature/Budgets/BudgetsPageValidationHardeningTest.php`.
- [x] Performance hardening wave completed (reports + inbox + retry throughput):
  - Communications Recovery Desk now computes expensive recovery summary only when `delivery` tab is active and data is loaded.
  - Request Reports metrics now use DB-side aggregate query (single pass for totals/in-review/decision-rate inputs) instead of repeated filtered scans.
  - Request Reports approval-step metrics now use DB subqueries (`whereIn` subselect) instead of loading request IDs into PHP memory.
  - Reports Center activity stream now uses DB `UNION ALL` aggregation + SQL pagination (row loading bounded to page size) instead of in-memory collection merge/sort/pagination.
  - Participating activity tables in this stream: `requests`, `expenses`, `vendor_invoices`, `assets`, `department_budgets`, `reconciliation_exceptions`, `tenant_pilot_wave_outcomes`.
  - Activity-stream index coverage completed for stream sort key (`occurred_at` source timestamp):
    - Added migration: `database/migrations/2026_03_08_130000_add_activity_stream_timestamp_indexes.php`.
    - New composites added: `expenses`, `vendor_invoices`, `assets`, `department_budgets`, `reconciliation_exceptions` on `(company_id, updated_at)` and `(company_id, created_at)`.
    - Existing coverage retained: `requests` (`company_id` + `updated_at` variants, `company_id` + `created_at`), `tenant_pilot_wave_outcomes` (`company_id`, `decision_at`).
  - Communication retry services (`request`, `vendor`, `asset`) now enforce configurable max batch caps and process in chunks to stabilize memory and throughput.
  - Request/Vendor/Asset communication CLI commands and the Communications Recovery Desk now use centralized retry batch/older-than guardrails from `config/communications.php`.
  - Dashboard and Reports Center now use short-lived performance cache snapshots (disabled in testing) via `config/performance.php`.
- [x] Regression tests added and passing:
  - `tests/Feature/Auth/PlatformOperatorTenantBoundaryTest.php`
  - `tests/Feature/Execution/ExecutionWebhookRateLimitTest.php`
  - `tests/Feature/Vendors/VendorStatementEndpointHardeningTest.php`
  - `tests/Feature/Finance/ProcurementAuthorizationMatrixTest.php`
- [x] Validation hardening regression tests added and passing:
  - `tests/Feature/Finance/TreasuryReconciliationWorkflowTest.php` (tenant-bound import + invalid resolution action payload)
  - `tests/Feature/Requests/CommunicationsRecoveryDeskPageTest.php` (delivery tab tamper guard)
  - `tests/Feature/Requests/RequestReportsValidationHardeningTest.php`
- [x] Full automated test suite green after hardening updates (`php artisan test`: 265 passed, 0 failed).

## Previous Progress Update (2026-02-27)
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
