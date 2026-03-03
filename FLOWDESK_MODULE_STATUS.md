# Flowdesk Module Status (Ground Truth)

Last updated: 2026-03-03

This file is the canonical module inventory so planning discussions stay aligned to what is already implemented in code.

## 1) Tenant Application Modules

## Dashboard
- Route: `/dashboard` via `dashboard`
- Entry: `app/Livewire/Dashboard/DashboardShell.php`
- Status: Implemented and routed.

## Requests & Approvals
- Routes: `/requests`, `/requests/communications`, `/requests/reports`
- Entries:
  - `app/Livewire/Requests/RequestsPage.php`
  - `app/Livewire/Requests/RequestCommunicationsPage.php`
  - `app/Livewire/Requests/RequestReportsPage.php`
  - `app/Livewire/Organization/ApprovalWorkflowsPage.php`
- Status: Implemented with approvals, communication logs, reporting views.
- Test coverage:
  - `tests/Feature/Requests/RequestApprovalAutomationTest.php`
  - `tests/Feature/Requests/RequestApprovalHierarchyTest.php`

## Expenses
- Route: `/expenses`
- Entry: `app/Livewire/Expenses/ExpensesPage.php`
- Status: Implemented.
- Test coverage: `tests/Feature/Expenses/ExpenseModuleTest.php`

## Vendors
- Routes: `/vendors`, `/vendors/{vendor}`, `/vendors/reports`, statement export/print
- Entries:
  - `app/Livewire/Vendors/VendorsPage.php`
  - `app/Livewire/Vendors/VendorDetailsPage.php`
  - `app/Livewire/Vendors/VendorReportsPage.php`
- Status: Implemented (profiles, invoices/payments timeline, reporting/export paths).
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
- Routes: `/departments`, `/team`, `/approval-workflows`
- Entries:
  - `app/Livewire/Organization/DepartmentsPage.php`
  - `app/Livewire/Organization/TeamPage.php`
  - `app/Livewire/Organization/ApprovalWorkflowsPage.php`
- Status: Implemented.
- Test coverage:
  - `tests/Feature/Organization/OrganizationHierarchyManagementTest.php`
  - `tests/Feature/Organization/SeatGovernanceTest.php`
  - `tests/Feature/Organization/TeamAvatarTest.php`

## Tenant Settings
- Routes under `/settings/*`
- Entries:
  - `app/Livewire/Settings/CommunicationSettingsPage.php`
  - `app/Livewire/Settings/RequestConfigurationPage.php`
  - `app/Livewire/Settings/ApprovalTimingControlsPage.php`
  - `app/Livewire/Settings/ExpenseControlsPage.php`
  - `app/Livewire/Settings/VendorControlsPage.php`
  - `app/Livewire/Settings/AssetControlsPage.php`
- Status: Implemented.
- Test coverage:
  - `tests/Feature/Settings/ApprovalTimingControlsTest.php`

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
- Route: `/platform/operations/execution`
- Entry: `app/Livewire/Platform/ExecutionOperationsPage.php`
- Status: Implemented with retry/recovery/reconcile workflows, incident cards, runbook links, and auto-recovery summary table.
- Test coverage:
  - `tests/Feature/Execution/ExecutionOperationsCenterPhaseFiveTest.php`

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

## 4) Communications Stack Status

## Already implemented in Flowdesk
- Channels: `in_app`, `email`, `sms` via company communication settings.
- Channel delivery adapters/managers for request, vendor, and asset pipelines.
- Retry services and scheduled queue processing commands for communications.
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
- Inactive by default in plan matrix: `ai`, `fintech`

Source files:
- `config/tenant_plans.php`
- `app/Services/TenantModuleAccessService.php`
- `app/Services/TenantPlanDefaultsService.php`

## 6) Gaps / Not Fully Enabled

1. `ai` module: entitlement key exists, but no active AI module routes/pages in current app nav.
2. `fintech` module: entitlement key exists, but no dedicated tenant-facing fintech module route/page.
3. Slack/Telegram provider adapters are intentionally deferred; execution alerts currently deliver through tenant-configured `in_app` + `email` channels only.

## 7) Ground Rule

Before proposing "new" module work, check this file plus route map (`routes/web.php`, `routes/console.php`) to avoid suggesting already-implemented capabilities.


## 8) Procurement and Treasury (In Progress)

## Procurement Foundation
- Status: In progress (Sprint 1 schema + base models added).
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
- Status: In progress (Sprint 1 schema + base models added).
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

## Sprint 3 Progress (In Progress)

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
- Linked from Procurement Orders page with quick navigation button.

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

## Sprint 6 Progress (Partial)
- Implemented:
  - Auto-match rules with confidence scoring and exception generation (`AutoReconcileStatementService`).
  - Reconciliation exception queue and resolution workflow (`TreasuryReconciliationExceptionsPage`).
  - Reconciliation metrics surfaced in Reports Center.
- Remaining for full Sprint 6 completion:
  - deeper beneficiary/text heuristics tuning and configurable rule matrix,
  - explicit execution reversal/failed-settlement linkage into treasury exception triage,
  - dedicated aging priority indicators and value-weighted queue ordering in treasury exception list.


