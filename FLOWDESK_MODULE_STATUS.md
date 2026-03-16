# Flowdesk Module Status (Ground Truth)

Last updated: 2026-03-16

This file is the canonical module inventory so planning discussions stay aligned to what is already implemented in code.

## 1) Tenant Application Modules

## Dashboard
- Route: `/dashboard` via `dashboard`
- Entry: `app/Livewire/Dashboard/DashboardShell.php`
- Status: Implemented and routed.

## Execution (Tenant)
- Routes: `/execution/health`, `/execution/payout-ready`, `/execution/help`
- Entries:
  - `app/Livewire/Execution/ExecutionHealthPage.php`
  - `app/Livewire/Execution/PayoutReadyQueuePage.php`
  - `app/Livewire/Execution/ExecutionUsageGuidePage.php`
- Status: Implemented with tenant-scoped execution health summary, payout working queue (`Run Payout` and `Rerun Payout`), in-app Help / Usage Guide, and AI-gated `Use Flow Agent` payout-risk advisory on queue rows.
- Test coverage:
  - `tests/Feature/Execution/TenantExecutionHealthPageTest.php`
  - `tests/Feature/Execution/TenantPayoutReadyQueuePageTest.php`
  - `tests/Feature/Execution/TenantExecutionUsageGuidePageTest.php`
- Usage guides:
  - `FLOWDESK_EXECUTION_OPERATIONS_USAGE.md`

## Procurement (Tenant)
- Routes: `/procurement/release-desk`, `/procurement/release-help`, `/procurement/orders`, `/procurement/receipts`, `/procurement/match-exceptions`
- Entries:
  - `app/Livewire/Procurement/ProcurementReleaseDeskPage.php`
  - `app/Livewire/Procurement/ProcurementReleaseGuidePage.php`
  - `app/Livewire/Procurement/PurchaseOrdersPage.php`
  - `app/Livewire/Procurement/PurchaseReceiptsPage.php`
  - `app/Livewire/Procurement/ProcurementMatchExceptionsPage.php`
- Status: Implemented with a single sidebar entry (`Manage Procurement`) and Release Desk as the primary operator workspace. Match Exceptions page now includes AI-gated `Use Flow Agent` advisory guidance (`why blocked`, risk level, next action).
- Usage guides:
  - `FLOWDESK_PROCUREMENT_RELEASE_DESK_USAGE.md`
## Treasury (Tenant)
- Routes: `/treasury/reconciliation`, `/treasury/reconciliation/help`, `/treasury/reconciliation/exceptions`, `/treasury/payment-runs`, `/treasury/cash-position`
- Entries:
  - `app/Livewire/Treasury/TreasuryReconciliationPage.php`
  - `app/Livewire/Treasury/TreasuryReconciliationGuidePage.php`
  - `app/Livewire/Treasury/TreasuryReconciliationExceptionsPage.php`
  - `app/Livewire/Treasury/TreasuryPaymentRunsPage.php`
  - `app/Livewire/Treasury/TreasuryCashPositionPage.php`
- Status: Implemented with Daily Reconciliation Desk as the single execution workspace for import, unmatched lines, exception decisions, and close-day checklist. Treasury exceptions now include AI-gated `Use Flow Agent` advisory guidance (`suggested match`, confidence, why blocked, and next action) on both desk preview and exceptions queue pages.
- Usage guides:
  - `FLOWDESK_TREASURY_DAILY_RECONCILIATION_DESK_USAGE.md`
## Requests & Approvals
- Routes: `/requests`, `/requests/lifecycle-desk`, `/requests/lifecycle-help`, `/requests/communications`, `/requests/communications/help`, `/requests/reports`
- Entries:
  - `app/Livewire/Requests/RequestsPage.php`
  - `app/Livewire/Requests/RequestLifecycleDeskPage.php`
  - `app/Livewire/Requests/RequestLifecycleGuidePage.php`
  - `app/Livewire/Requests/RequestCommunicationsPage.php`
  - `app/Livewire/Requests/RequestReportsPage.php`
  - `app/Livewire/Organization/ApprovalWorkflowsPage.php`
- Status: Implemented with a consolidated Request Lifecycle Desk (approved -> procurement -> payout dispatch -> outcomes), plus communications recovery, approvals, and reports.
- Test coverage:
  - `tests/Feature/Requests/RequestApprovalAutomationTest.php`
  - `tests/Feature/Requests/RequestApprovalHierarchyTest.php`
  - `tests/Feature/Requests/CommunicationsRecoveryDeskPageTest.php`
  - `tests/Feature/Requests/RequestLifecycleDeskPageTest.php`
- Usage guides:
  - `FLOWDESK_REQUEST_LIFECYCLE_DESK_USAGE.md`
  - `FLOWDESK_COMMUNICATIONS_RECOVERY_DESK_USAGE.md`

## Expenses
- Route: `/expenses`
- Entry: `app/Livewire/Expenses/ExpensesPage.php`
- Status: Implemented.
- Test coverage: `tests/Feature/Expenses/ExpenseModuleTest.php`

## Vendors
- Routes: `/vendors` (Vendor Command Center), `/vendors/registry`, `/vendors/{vendor}`, `/vendors/reports`, statement export/print
- Entries:
  - `app/Livewire/Vendors/VendorCommandCenterPage.php`
  - `app/Livewire/Vendors/VendorsPage.php`
  - `app/Livewire/Vendors/VendorDetailsPage.php`
  - `app/Livewire/Vendors/VendorReportsPage.php`
- Status: Implemented with Vendor Command Center (`/vendors`) as the primary workspace; registry/detail/reports remain drill-down pages.
- Test coverage: `tests/Feature/Vendors/VendorModuleTest.php`

## Budgets
- Route: `/budgets`
- Entry: `app/Livewire/Budgets/BudgetsPage.php`
- Status: Implemented.
- Test coverage: `tests/Feature/Budgets/BudgetModuleTest.php`

## Assets
- Routes: `/assets`, `/assets/reports`
- Entries:
  - `app/Livewire/Assets/AssetsPage.php`
  - `app/Livewire/Assets/AssetReportsPage.php`
- Status: Implemented (with reminder/communication pipeline services).
- Test coverage:
  - `tests/Feature/Assets/AssetModuleTest.php`
  - `tests/Feature/Assets/AssetReminderServiceTest.php`

## Reports Center
- Route: `/reports`
- Entry: `app/Livewire/Reports/ReportsCenterPage.php`
- Status: Implemented.
- Test coverage: `tests/Feature/Reports/ReportsCenterTest.php`

## Organization
- Routes: `/organization/admin-desk`, `/departments`, `/team`, `/approval-workflows`
- Entries:
  - `app/Livewire/Organization/OrganizationAdminDeskPage.php`
  - `app/Livewire/Organization/DepartmentsPage.php`
  - `app/Livewire/Organization/TeamPage.php`
  - `app/Livewire/Organization/ApprovalWorkflowsPage.php`
- Status: Implemented with Organization Admin Desk (`/organization/admin-desk`) as the primary owner workspace for departments/team/workflow governance.
- Test coverage:
  - `tests/Feature/Organization/OrganizationHierarchyManagementTest.php`
  - `tests/Feature/Organization/SeatGovernanceTest.php`
  - `tests/Feature/Organization/TeamAvatarTest.php`

## Tenant Settings
- Routes under `/settings/*` (primary: `/settings` and `/settings/control-center`)
- Entries:
  - `app/Livewire/Settings/SettingsControlCenterPage.php`
  - `app/Livewire/Settings/CommunicationSettingsPage.php`
  - `app/Livewire/Settings/RequestConfigurationPage.php`
  - `app/Livewire/Settings/ApprovalTimingControlsPage.php`
  - `app/Livewire/Settings/ExpenseControlsPage.php`
  - `app/Livewire/Settings/VendorControlsPage.php`
  - `app/Livewire/Settings/AssetControlsPage.php`
  - `app/Livewire/Settings/PaymentsRailsIntegrationPage.php`
- Status: Implemented with a consolidated Settings Control Center as the primary tenant settings workspace.
- Test coverage:
  - `tests/Feature/Settings/ApprovalTimingControlsTest.php`
  - `tests/Feature/Settings/SettingsControlCenterPageTest.php`
- Usage guide:
  - `FLOWDESK_SETTINGS_CONTROL_CENTER_USAGE.md`

## 2) Platform Modules

## Tenant / Organization Management
- Routes under `/platform/tenants*`
- Entries:
  - `app/Livewire/Settings/TenantManagementPage.php`
  - `app/Livewire/Platform/TenantProfilePage.php`
  - `app/Livewire/Settings/TenantDetailsPage.php`
  - `app/Livewire/Platform/TenantPlanEntitlementsPage.php`
- Status: Implemented.
- Test coverage:
  - `tests/Feature/Settings/TenantModuleEntitlementTest.php`
  - `tests/Feature/Settings/TenantBillingOpsTest.php`

## Platform Users
- Route: `/platform/users`
- Entry: `app/Livewire/Platform/PlatformUsersPage.php`
- Status: Implemented.

## Tenant Execution Mode / Policy
- Routes:
  - `/platform/tenants/{company}/execution-mode`
  - `/platform/tenants/{company}/execution-policy`
- Entries:
  - `app/Livewire/Platform/TenantExecutionModePage.php`
  - `app/Livewire/Platform/TenantExecutionPolicyPage.php`
- Status: Implemented.

## Execution Operations
- Routes: `/platform/operations`, `/platform/operations/execution`, `/platform/operations/ai-runtime-health`
- Entries:
  - `app/Livewire/Platform/PlatformOperationsHubPage.php`
  - `app/Livewire/Platform/ExecutionOperationsPage.php`
  - `app/Livewire/Platform/AiRuntimeHealthPage.php`
- Status: Implemented with consolidated Operations Hub (`/platform/operations`) as primary platform workspace with tabs for execution ops, checklist, incident timeline, and pilot rollout. Includes AI Runtime Health monitor for model/OCR capability checks plus cross-tenant receipt-analysis fallback trends.
- Test coverage:
  - `tests/Feature/Execution/ExecutionOperationsCenterPhaseFiveTest.php`
  - `tests/Feature/Execution/AiRuntimeHealthPageTest.php`

## Execution Test Checklist
- Route: `/platform/operations/execution-checklist`
- Entry: `app/Livewire/Platform/ExecutionTestChecklistPage.php`
- Status: Implemented.
- Test coverage:
  - `tests/Feature/Execution/ExecutionTestChecklistPageTest.php`

## Incident History
- Route: `/platform/operations/incident-history`
- Entry: `app/Livewire/Platform/IncidentHistoryPage.php`
- Status: Implemented with incident filters, seven-day trend chart, metadata drill-down, and CSV export.
- Test coverage:
  - `tests/Feature/Execution/IncidentHistoryPageTest.php`

## Pilot Rollout KPI Capture
- Route: `/platform/operations/pilot-rollout`
- Entry: `app/Livewire/Platform/PilotRolloutKpiPage.php`
- Status: Implemented with baseline/pilot window capture form, tenant filters, latest-delta panel, and pilot wave outcome recording (`go`/`hold`/`no-go`) with notes + audit trail.
- Test coverage:
  - `tests/Feature/Execution/PilotRolloutKpiPageTest.php`

## 3) Execution Engine Status

## Implemented
- Billing orchestration + processing + webhook reconciliation.
- Request payout execution + webhook reconciliation.
- Platform execution operations center and manual actions.
- Execution alert summary command and scheduled runs.
- Auto-recovery command/service with cooldown + batch caps.
- Auto-recovery run summaries persisted for UI reporting.

## Key files
- `app/Services/Execution/*`
- `routes/console.php` (`execution:ops:alert-summary`, `execution:ops:auto-recover`, `rollout:pilot:capture-kpis`)
- `config/execution.php`

## Execution tests
- `tests/Feature/Execution/SubscriptionAutoBillingPhaseThreeTest.php`
- `tests/Feature/Execution/RequestPayoutExecutionPhaseFourTest.php`
- `tests/Feature/Execution/ExecutionOperationsCenterPhaseFiveTest.php`
- `tests/Feature/Execution/ExecutionOpsGuardrailsTest.php`
- `tests/Feature/Execution/TenantExecutionHealthPageTest.php`
- `tests/Feature/Execution/TenantPayoutReadyQueuePageTest.php`
- `tests/Feature/Execution/TenantExecutionUsageGuidePageTest.php`

## 4) Communications Stack Status

## Already implemented in Flowdesk
- Channels: `in_app`, `email`, `sms` via company communication settings.
- Channel delivery adapters/managers for request, vendor, and asset pipelines.
- Retry services and scheduled queue processing commands for communications.
- Tenant Communications Recovery Desk and help page: `/requests/communications`, `/requests/communications/help`.
- SMS provider abstraction (`Termii`) wired in communication layer.

## Key files
- `app/Domains/Company/Models/CompanyCommunicationSetting.php`
- `app/Services/RequestCommunicationDeliveryManager.php`
- `app/Services/VendorCommunicationDeliveryManager.php`
- `app/Services/AssetCommunicationDeliveryManager.php`
- `app/Services/RequestCommunicationRetryService.php`
- `app/Services/VendorCommunicationRetryService.php`
- `app/Services/AssetCommunicationRetryService.php`
- `app/Services/RequestCommunication/Sms/TermiiSmsProvider.php`

## Execution-alert channel delivery status
- `execution:ops:alert-summary` now dispatches tenant-scoped alert notifications via existing communication settings channels (`in_app`, `email`) and records delivery outcomes in `tenant_audit_events`.

## 5) Entitlement/Plan Matrix Status

Module entitlements are active and enforced:
- `requests`, `expenses`, `vendors`, `budgets`, `assets`, `reports`, `communications`
- Inactive by default in plan matrix: `ai`, `fintech` (used for Payments Rails Integration scope).

Source files:
- `config/tenant_plans.php`
- `app/Services/TenantModuleAccessService.php`
- `app/Services/TenantPlanDefaultsService.php`

## 6) Gaps / Not Fully Enabled

1. `ai` module: entitlement key exists, but no active AI module routes/pages in current app nav.
2. Payments Rails Integration (fintech entitlement): tenant route `/settings/payments-rails` now includes staged rollout enforcement, sandbox-aware Connect/Test/Sync/Pause actions, webhook readiness validation, and health/audit tracking. Full settlement-feed ingestion is still pending.
3. Slack/Telegram provider adapters are intentionally deferred; execution alerts currently deliver through tenant-configured `in_app` + `email` channels only.

## 6.1) Later Modules / Deferred Backlog (Non-AI Priority)

1. Payments Rails Integration (fintech entitlement, post-core)
   - Tenant-facing status/config shell now implemented at `/settings/payments-rails`; expand to guided provider onboarding and rail sync operations.
   - Initial capabilities scoped for organization-level financial rails (to be defined per rollout phase).
2. Execution alert channel expansion
   - Add Slack adapter for `execution:ops:alert-summary` delivery pipeline.
   - Add Telegram adapter for `execution:ops:alert-summary` delivery pipeline.
3. Production hardening track (post-feature)

Scope reference:
- `FLOWDESK_PAYMENTS_RAILS_SCOPE.md`
   - Scheduler and queue observability dashboards/alerts.
   - Cutover runbook rehearsal with seeded tenant UAT data and rollback checkpoints.

## 7) Ground Rule

Before proposing "new" module work, check this file plus route map (`routes/web.php`, `routes/console.php`) to avoid suggesting already-implemented capabilities.


## 8) Procurement and Treasury (Implemented)

## Procurement Foundation
- Status: Implemented (Sprint 1 schema + base models added).
- New schema:
  - `purchase_orders`
  - `purchase_order_items`
  - `goods_receipts`
  - `goods_receipt_items`
  - `procurement_commitments`
  - `invoice_match_results`
  - `invoice_match_exceptions`
- New domain models:
  - `app/Domains/Procurement/Models/*`

## Treasury Foundation
- Status: Implemented (Sprint 1 schema + base models added).
- New schema:
  - `bank_accounts`
  - `bank_statements`
  - `bank_statement_lines`
  - `payment_runs`
  - `payment_run_items`
  - `reconciliation_matches`
  - `reconciliation_exceptions`
- New domain models:
  - `app/Domains/Treasury/Models/*`

## New tests
- `tests/Feature/Finance/ProcurementTreasuryFoundationTest.php`

## Sprint 2 Progress (Implemented)

### Request -> PO Conversion
- New service: `app/Services/Procurement/CreatePurchaseOrderFromRequestService.php`
- New policy gate: `RequestPolicy::convertToPurchaseOrder`
- Request UI action added: `Convert to PO` in request detail modal (`RequestsPage`).
- New linkage in request detail card: `Procurement Handoff` with linked PO status.

### PO Issuance + Commitment Posting
- New service: `app/Services/Procurement/PurchaseOrderIssuanceService.php`
- Issuing draft PO now posts `procurement_commitments` (tenant-configurable switch).
- Tenant audit events added for:
  - `tenant.procurement.purchase_order.created_from_request`
  - `tenant.procurement.purchase_order.issued`
  - `tenant.procurement.commitment.posted`

### Procurement UI
- New tenant module route: `/procurement/orders` (`procurement.orders`).
- New page/component:
  - `resources/views/app/procurement/orders.blade.php`
  - `app/Livewire/Procurement/PurchaseOrdersPage.php`
  - `resources/views/livewire/procurement/purchase-orders-page.blade.php`

### Tenant-Scoped Controls (Configurable)
- New settings tables:
  - `company_procurement_control_settings`
  - `company_treasury_control_settings`
- New settings services:
  - `app/Services/Procurement/ProcurementControlSettingsService.php`
  - `app/Services/Treasury/TreasuryControlSettingsService.php`
- New owner settings pages:
  - `/settings/procurement-controls`
  - `/settings/treasury-controls`

### Entitlements Expanded
- New entitlement keys implemented and enforced:
  - `procurement`
  - `treasury`
- Wired through:
  - `tenant_feature_entitlements` model + migration columns
  - `config/tenant_plans.php`
  - `TenantPlanDefaultsService`
  - `TenantModuleAccessService`
  - Platform tenant plan/modules page checkboxes

### Sprint 2 tests
- `tests/Feature/Finance/ProcurementRequestToPoFlowTest.php`
- `tests/Feature/Settings/TenantModuleEntitlementTest.php` (procurement route case)

## Sprint 3 Progress (Implemented)

### Goods Receipt Workflow
- New tenant route: `/procurement/receipts` (`procurement.receipts`) with dedicated receipts table and detail modal.
- Added CSV export button on receipts page (uses active filters).
- New service: `app/Services/Procurement/CreateGoodsReceiptService.php`
- Procurement Orders detail modal now supports recording receipts with line-level quantities and unit costs.
- PO line received balances are updated on each receipt and PO status auto-progresses:
  - `issued` -> `part_received`
  - `part_received` -> `received`
- Tenant audit event: `tenant.procurement.goods_receipt.created`.

### Vendor Invoice to PO Linking
- New migration: `2026_03_03_000700_add_purchase_order_link_to_vendor_invoices_table.php`.
- `vendor_invoices` now has optional `purchase_order_id` for traceable PO linkage.
- New service: `app/Services/Procurement/LinkVendorInvoiceToPurchaseOrderService.php`.
- Linking invoice to PO can transition PO to `invoiced` where applicable.
- Tenant audit events:
  - `tenant.procurement.vendor_invoice.linked`
  - `tenant.procurement.purchase_order.invoiced`

### Procurement Controls Expanded
- Added tenant-configurable controls:
  - `receipt_allowed_roles`
  - `invoice_link_allowed_roles`
  - `allow_over_receipt`
- Updated settings page: `/settings/procurement-controls`.

### Sprint 3 tests
- `tests/Feature/Finance/ProcurementReceiptAndInvoiceLinkingTest.php`

## Sprint 4 Progress (Implemented)

### 3-Way Match Engine + Payment Blocking
- New service: `app/Services/Procurement/EvaluateInvoiceThreeWayMatchService.php`
  - Evaluates PO vs receipt vs invoice using tenant-configurable tolerances.
  - Writes `invoice_match_results` and regenerates open `invoice_match_exceptions`.
- New service: `app/Services/Procurement/ProcurementPaymentGateService.php`
  - Blocks payout queueing when procurement controls require strict match state.
- Payout orchestration integration:
  - `app/Services/Execution/RequestPayoutExecutionOrchestrator.php`
  - Adds procurement gate metadata and audit event when payout queueing is blocked.

### Procurement controls expanded
- Added tenant-configurable controls:
  - `match_amount_tolerance_percent`
  - `match_quantity_tolerance_percent`
  - `match_date_tolerance_days`
  - `block_payment_on_mismatch`
  - `match_override_allowed_roles`
- Updated in:
  - `config/procurement.php`
  - `ProcurementControlSettingsService`
  - `CompanyProcurementControlSetting`
  - `/settings/procurement-controls` UI

### Procurement exception workbench
- Route: `/procurement/match-exceptions` (`procurement.match-exceptions`)
- New page/component:
  - `app/Livewire/Procurement/ProcurementMatchExceptionsPage.php`
  - `resources/views/livewire/procurement/procurement-match-exceptions-page.blade.php`
- Linked from Procurement Release Desk and Procurement Orders.
### Procurement Release Desk consolidation
- Primary route: `/procurement/release-desk` (`procurement.release-desk`)
- Help route: `/procurement/release-help` (`procurement.release-help`)
- New page/components:
  - `app/Livewire/Procurement/ProcurementReleaseDeskPage.php`
  - `resources/views/livewire/procurement/procurement-release-desk-page.blade.php`
  - `app/Livewire/Procurement/ProcurementReleaseGuidePage.php`
  - `resources/views/livewire/procurement/procurement-release-guide-page.blade.php`
- Sidebar navigation is intentionally consolidated to one entry (`Manage Procurement`).
- Subpages (`orders`, `receipts`, `match-exceptions`) include back navigation to Release Desk.
- Usage guides:
  - `FLOWDESK_PROCUREMENT_RELEASE_DESK_USAGE.md`

### Sprint 4 tests
- `tests/Feature/Finance/ProcurementReceiptAndInvoiceLinkingTest.php`
- `tests/Feature/Execution/RequestPayoutExecutionPhaseFourTest.php`
- `tests/Feature/Settings/TenantModuleEntitlementTest.php`

## Sprint 5 Progress (Implemented)

### Treasury import + reconciliation workbench
- New treasury routes/pages:
  - `/treasury/reconciliation`
  - `/treasury/reconciliation/exceptions`
  - `/treasury/payment-runs`
  - `/treasury/cash-position`
- New services:
  - `app/Services/Treasury/ImportBankStatementCsvService.php`
  - `app/Services/Treasury/AutoReconcileStatementService.php`
- Capabilities now available:
  - Bank account setup
  - CSV statement import with idempotent line hashing
  - Auto-reconcile against payout attempts + direct expenses
  - Exception queue with resolve/waive action and required notes
  - Audit events for import and reconciliation runs/actions

### Treasury visibility in Reports Center
- `app/Livewire/Reports/ReportsCenterPage.php` now includes treasury metrics:
  - reconciled lines
  - open reconciliation exceptions
  - unreconciled value
- Treasury exceptions are also included in unified report activity feed.

### Sprint 5 tests
- `tests/Feature/Finance/TreasuryReconciliationWorkflowTest.php`
- `tests/Feature/Reports/ReportsCenterTest.php`

## Sprint 6 Progress (Implemented)
- Implemented:
  - Auto-match rules with confidence scoring and exception generation (`AutoReconcileStatementService`).
  - Reconciliation exception queue and resolution workflow (`TreasuryReconciliationExceptionsPage`).
  - Reconciliation metrics surfaced in Reports Center.
- Completed for Sprint 6 scope:
  - deeper beneficiary/text heuristics tuning and configurable rule matrix.
  - explicit execution reversal/failed-settlement linkage into treasury exception triage.
  - dedicated aging priority indicators and value-weighted queue ordering in treasury exception list.

## 9) Program Closeout (2026-03-03)
- Sprint 7 governance hardening and controls: completed.
- Sprint 8 rollout controls and enablement: completed.
- Regression validation: php artisan test passed (198 tests, 0 failures).
- UAT dry-run validation: php artisan procurement:backfill-vendor-links --dry-run completed with 0 errors and no persisted changes.

## 9.1) Security Hardening Update (2026-03-08)
- Route boundary:
  - Platform operators are explicitly blocked from tenant route surface via `company.context` middleware (`EnsureCompanyContext`).
- Tenant query scoping hardening:
  - Added explicit `company_id` filters in critical dashboard and reports query paths as defense-in-depth beyond model scopes.
- Sensitive endpoint throttling:
  - `execution-webhooks`: `/webhooks/execution/{provider}`
  - `tenant-downloads`: request/expense/vendor attachment downloads
  - `tenant-exports`: vendor statement export/print
- Input validation hardening:
  - Vendor statement export/print controllers now strictly validate `from`, `to`, and `invoice_status`.
- Validation hardening (Treasury + Requests):
  - Treasury reconciliation statement import now enforces tenant-bound bank account selection using explicit `exists` constraints (`company_id` + `is_active`).
  - Treasury exception resolution payloads now enforce allow-listed `resolutionAction` values (`resolved`, `waived`) in both reconciliation desk and exceptions workbench.
  - Treasury workbench filters now normalize status/severity/stream/type/per-page/search inputs to safe allow-lists before query execution.
  - Request reports filters now normalize status/type/department/date/per-page/search inputs and include explicit `company_id` filter in base query.
  - Communications recovery desk now normalizes tab/filter state and prevents delivery-log tab forcing through tampered Livewire state.
- Authorization matrix hardening (Vendors + Procurement):
  - Added procurement policies and policy registration:
    - `app/Policies/PurchaseOrderPolicy.php`
    - `app/Policies/GoodsReceiptPolicy.php`
    - `app/Policies/InvoiceMatchExceptionPolicy.php`
  - Procurement pages now use policy-based workspace access checks and order action checks.
  - Vendor statement hardening tests now include explicit role/policy denial coverage.
- Authorization matrix hardening (Treasury + Requests):
  - Added treasury policies and policy registration:
    - `app/Policies/BankStatementPolicy.php`
    - `app/Policies/BankAccountPolicy.php`
    - `app/Policies/PaymentRunPolicy.php`
    - `app/Policies/ReconciliationExceptionPolicy.php`
  - Treasury workspaces now use policy-based access checks:
    - `TreasuryReconciliationPage`
    - `TreasuryReconciliationExceptionsPage`
    - `TreasuryReconciliationGuidePage`
    - `TreasuryPaymentRunsPage`
    - `TreasuryCashPositionPage`
  - Request guide/lifecycle entry checks now use `SpendRequest` policy gate (`viewAny`) for consistent active-user enforcement.
- Authorization matrix hardening (Execution tenant workspace):
  - Added `app/Policies/RequestPayoutExecutionAttemptPolicy.php` and policy registration in `AuthServiceProvider`.
  - `ExecutionHealthPage`, `PayoutReadyQueuePage`, and `ExecutionUsageGuidePage` now authorize tenant access through policy (`viewAny`) instead of inline role lists.
  - Payout run action access now uses policy ability `queueAny` (owner/finance/manager), keeping read-only execution users limited to monitoring.
  - Regression coverage added: `TenantPayoutReadyQueuePageTest` now asserts auditors cannot trigger manual payout queue runs.
- Validation hardening (Expenses workspace):
  - `ExpensesPage` now normalizes filter state (`status`, `payment_method`, `vendor`, `department`, `dateFrom/dateTo`, `perPage`) to allow-lists/strict date format before query execution.
  - `ExpensesPage` form validation now enforces tenant-bound `exists` checks for `department_id`, `vendor_id`, and `paid_by_user_id`.
  - Added regression coverage: `tests/Feature/Expenses/ExpensesPageValidationHardeningTest.php`.
- Validation hardening (Budgets workspace):
  - `BudgetsPage` now normalizes filter state (`department`, `status`, `period_type`, `perPage`) to allow-lists before query execution.
  - `BudgetsPage` form validation now enforces tenant-bound `exists` check for `department_id`.
  - Added regression coverage: `tests/Feature/Budgets/BudgetsPageValidationHardeningTest.php`.
- Added hardening regression coverage:
  - `tests/Feature/Auth/PlatformOperatorTenantBoundaryTest.php`
  - `tests/Feature/Execution/ExecutionWebhookRateLimitTest.php`
  - `tests/Feature/Vendors/VendorStatementEndpointHardeningTest.php`
  - `tests/Feature/Finance/ProcurementAuthorizationMatrixTest.php`
  - `tests/Feature/Finance/TreasuryAuthorizationMatrixTest.php`
  - `tests/Feature/Requests/RequestGuidesAuthorizationTest.php`
  - `tests/Feature/Finance/TreasuryReconciliationWorkflowTest.php` (tenant import validation + invalid resolution action checks)
  - `tests/Feature/Requests/CommunicationsRecoveryDeskPageTest.php` (delivery tab tamper guard)
  - `tests/Feature/Requests/RequestReportsValidationHardeningTest.php`
- Validation snapshot after update:
  - `php artisan test` passed (`264 passed`, `0 failed`).

## 9.2) Performance Hardening Update (2026-03-08)
- Reports and inbox optimization pass completed for high-volume request operations surfaces:
  - `RequestCommunicationsPage` now skips recovery summary query work unless the delivery tab is active and hydrated, reducing inbox-tab render load.
  - `RequestReportsPage` metrics now use DB-side aggregate projection for totals/in-review/approval-rate inputs instead of multiple repeated scans.
  - `RequestReportsPage` overdue/escalated step metrics now use subquery scoping (`whereIn` subselect) rather than plucking request IDs into PHP memory.
  - `ReportsCenterPage` activity stream now executes DB `UNION ALL` aggregation + ordered SQL pagination (bounded row loading at page size) instead of loading/sorting all module rows in memory.
  - Activity stream source tables: `requests`, `expenses`, `vendor_invoices`, `assets`, `department_budgets`, `reconciliation_exceptions`, `tenant_pilot_wave_outcomes`.
  - Activity-stream index coverage completed for activity sort key (`occurred_at` source timestamp):
    - Added migration `database/migrations/2026_03_08_130000_add_activity_stream_timestamp_indexes.php`.
    - New composites: `expenses`, `vendor_invoices`, `assets`, `department_budgets`, `reconciliation_exceptions` on `(company_id, updated_at)` and `(company_id, created_at)`.
    - Existing coverage: `requests` (composite indexes including `updated_at` and `created_at` with `company_id`), `tenant_pilot_wave_outcomes` (`company_id`, `decision_at`).
- Queue throughput/retry tuning pass completed for communications stack:
  - Added `config/communications.php` for centralized retry guardrails (`max_batch_size`, defaults, `chunk_size`, `max_older_than_minutes`, UI batch defaults).
  - `RequestCommunicationRetryService`, `VendorCommunicationRetryService`, and `AssetCommunicationRetryService` now enforce batch caps and process retry workloads in chunks to keep memory stable.
  - Communication retry Artisan commands now clamp `--batch` and `--older-than` to configured guardrails.
- Expensive dashboard/report caching enabled:
  - Added `config/performance.php` cache controls.
  - `DashboardShell` now resolves a short-lived cached snapshot for metrics + role cards/actions/signals.
  - `ReportsCenterPage` now caches metrics payload by tenant/user/filter fingerprint.
  - Cache path is intentionally bypassed in testing to keep deterministic test behavior.
- Regression validation:
  - `tests/Feature/Requests/RequestApprovalAutomationTest.php`
  - `tests/Feature/Requests/CommunicationsRecoveryDeskPageTest.php`
  - `tests/Feature/Requests/RequestReportsValidationHardeningTest.php`
  - `tests/Feature/Dashboard/RoleSpecificDashboardTest.php`
  - `tests/Feature/Reports/ReportsCenterTest.php`
  - `tests/Feature/Vendors/VendorModuleTest.php`
- Validation snapshot after performance pass:
  - `php artisan test` passed (`265 passed`, `0 failed`).

## 9.3) Validation and UI Consistency Update (2026-03-09)
- Validation hardening (Vendors workspace):
  - `VendorsPage` now normalizes tamper-prone filter/query state and operator inputs:
    - list filters: `statusFilter`, `typeFilter`, `perPage`
    - invoice filters: `invoiceSearch`, `invoiceStatusFilter`
    - statement export filters: `statementDateFrom`, `statementDateTo`, `statementInvoiceStatus`
    - communication/retry controls: `reminderDaysAhead`, `vendorCommunicationPerPage`, `vendorCommQueuedOlderThanMinutes`
  - Added regression coverage: `tests/Feature/Vendors/VendorsPageValidationHardeningTest.php`.
  - Scope guard regression included: retry action remains scoped to selected vendor logs.
- Validation hardening (Assets + Procurement + Requests workspaces):
  - `AssetsPage` now normalizes search/status/category/assignment/per-page filters to strict allow-lists.
  - `PurchaseOrdersPage` now normalizes search/status/per-page filters and enforces tenant/order-scoped `exists` validation for goods-receipt line item IDs.
  - `PurchaseOrdersPage` invoice-link action now rejects cross-tenant / wrong-vendor / invalid invoice IDs before service execution.
  - `PurchaseReceiptsPage` now normalizes search/status/date-range/per-page filters with strict date parsing and bounded range behavior.
  - `RequestsPage` now normalizes search/status/type/department/scope/date-range/per-page filters to strict allow-lists before query execution.
  - Added regression coverage:
    - `tests/Feature/Assets/AssetsPageValidationHardeningTest.php`
    - `tests/Feature/Finance/ProcurementPagesValidationHardeningTest.php`
    - `tests/Feature/Requests/RequestsPageValidationHardeningTest.php`
- UI consistency rollout (Edit/View actions):
  - Added icons to Edit/View action buttons to match Expenses workspace style in:
    - `budgets-page`
    - `assets-page`
    - `purchase-orders-page`
    - `purchase-receipts-page`
    - `requests-page`
    - `vendors-page`
    - `vendor-details-page`
    - `approval-timing-controls-page`
    - `request-configuration-page`











## 10) Payments Rails Action Map (2026-03-07)
- Tenant route: `/settings/payments-rails` (owner-only, fintech-entitled).
- Implemented actions: `Connect`, `Test Connection`, `Sync Now`, `Pause/Resume` with sandbox provider probes, webhook readiness validation, and failed-action audits (`connect_failed`, `connection_test_failed`, `sync_failed`, `resume_failed`).
- Tenant audit visibility: `Recent Payments Rail Actions` (10 per page) on same tenant page.
- Platform audit visibility: `/platform/tenants/{company}/billing` -> `Tenant Audit Events` includes `tenant.payments_rails.*`.
- Alignment note: `/platform/operations/incident-history` remains execution-incident focused and does not currently include payments-rails action stream.
- AI roadmap reference: `FLOWDESK_AI_PLAN.md`

AI implementation foundation status (2026-03-07):
- Added local AI runtime config (`config/ai.php`) for low-cost stack (`Ollama`, local models, `Qdrant`).
- Added tenant AI feature gate service: `app/Services/AI/AiFeatureGateService.php` (checks `ai_enabled` per company).
- Added runtime profile service: `app/Services/AI/AiRuntimeProfileService.php` for consistent module consumption.
- Added unit coverage: `tests/Unit/AI/AiFeatureGateServiceTest.php`.

AI rollout increment (2026-03-16):
- Implemented Requests **Flow Agents** advisory service: `app/Services/AI/RequestFlowAgentService.php`.
- Integrated tenant-gated Flow Agents panel into Requests draft + view modals:
  - `app/Livewire/Requests/RequestsPage.php`
  - `resources/views/livewire/requests/requests-page.blade.php`
- Added user-triggered workflow execution path from Flow Agents actions (user-first, no autonomous execution).
- Updated modal action labels to "Use Flow Agent" (icon + clearer call-to-action).
- Added feature coverage for enabled/disabled entitlement behavior and advisory output:
  - `tests/Feature/Requests/RequestFlowAgentsTest.php`
- Expenses AI increment:
  - Added `app/Services/AI/ExpenseReceiptIntelligenceService.php` for receipt upload extraction with Ollama JSON inference + deterministic OCR/filename fallback.
  - Hardened extraction to handle plain-number amount formats and naira-symbol normalization edge cases.
  - Tuned confidence scoring to rely on structured signals so no-detection cases do not show inflated confidence.
  - Added explicit OCR diagnostics in Expenses UI when server binaries are unavailable (`tesseract`/`pdftotext`).
  - Switched Expenses feedback to floating toasts so validation/detection errors stay visible above modals.
  - Updated receipt action CTA to `Use Flow Agent` (with icon + helper label) and separated upload loading state from analyze loading state.
  - Added test coverage for model path with mocked Ollama response:
    - `tests/Feature/Expenses/ExpenseReceiptAgentTest.php::test_receipt_agent_uses_model_output_when_ollama_is_available`
  - Integrated Receipt Agent UX + duplicate preview guard + create-button diagnostics in:
    - `app/Livewire/Expenses/ExpensesPage.php`
    - `resources/views/livewire/expenses/expenses-page.blade.php`
  - Added coverage: `tests/Feature/Expenses/ExpenseReceiptAgentTest.php`.
- Platform AI runtime observability increment:
  - Added `app/Services/AI/AiRuntimeHealthService.php` for provider/model/OCR capability checks and 24h receipt-agent fallback metrics.
  - Added `/platform/operations/ai-runtime-health` monitor page:
    - `app/Livewire/Platform/AiRuntimeHealthPage.php`
    - `resources/views/livewire/platform/ai-runtime-health-page.blade.php`
  - Linked monitor from:
    - `resources/views/livewire/platform/platform-operations-hub-page.blade.php`
    - `resources/views/livewire/platform/execution-operations-page.blade.php`
  - Added coverage:
    - `tests/Feature/Execution/AiRuntimeHealthPageTest.php`
- Tenant execution payout-risk Flow Agent increment:
  - Added `app/Services/AI/PayoutRiskFlowAgentService.php` for advisory payout-risk scoring (retries, queue age, amount threshold, and tenant failure drift).
  - Integrated `Use Flow Agent` advisory action in tenant payout queue:
    - `app/Livewire/Execution/PayoutReadyQueuePage.php`
    - `resources/views/livewire/execution/payout-ready-queue-page.blade.php`
  - Added audit trail action:
    - `tenant.execution.payout.risk_analyzed`
  - Added coverage:
    - `tests/Feature/Execution/TenantPayoutReadyQueuePageTest.php::test_flow_agent_can_analyze_payout_risk_when_ai_is_enabled_for_tenant`
- Procurement match-exceptions Flow Agent increment:
  - Added `app/Services/AI/ProcurementMatchFlowAgentService.php` for deterministic exception analysis and guided remediation output.
  - Integrated `Use Flow Agent` into procurement exceptions workbench:
    - `app/Livewire/Procurement/ProcurementMatchExceptionsPage.php`
    - `resources/views/livewire/procurement/procurement-match-exceptions-page.blade.php`
  - Added audit trail action:
    - `tenant.procurement.match.exception.flow_agent_analyzed`
  - Added coverage:
    - `tests/Feature/Finance/ProcurementMatchFlowAgentTest.php`
- Treasury reconciliation Flow Agent increment:
  - Added `app/Services/AI/TreasuryReconciliationFlowAgentService.php` for deterministic reconciliation exception analysis and suggested-match guidance.
  - Integrated `Use Flow Agent` into treasury reconciliation surfaces:
    - `app/Livewire/Treasury/TreasuryReconciliationPage.php`
    - `resources/views/livewire/treasury/treasury-reconciliation-page.blade.php`
    - `app/Livewire/Treasury/TreasuryReconciliationExceptionsPage.php`
    - `resources/views/livewire/treasury/treasury-reconciliation-exceptions-page.blade.php`
  - Added audit trail action:
    - `tenant.treasury.reconciliation.exception.flow_agent_analyzed`
  - Added coverage:
    - `tests/Feature/Finance/TreasuryReconciliationFlowAgentTest.php`
