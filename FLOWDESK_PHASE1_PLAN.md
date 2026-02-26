# FLOWDESK PHASE 1 BUILD PLAN
This file defines the exact build order for Flowdesk Phase 1.

Codex must follow this plan step-by-step.

----------------------------------------------------

## GLOBAL RULE (READ ALWAYS)

Before executing ANY step:

1. Read AGENTS.md
2. Read FLOWDESK_ARCHITECTURE.md
3. Read FLOWDESK_DATABASE.md
4. Read FLOWDESK_CODING_RULES.md
5. Read FLOWDESK_UI_GUIDE.md

Follow them strictly.

All modules must:
- be multi-tenant (company_id scoped)
- enforce roles and permissions
- include activity logging
- use clean modern UI
- include loading states
- use slide-over panels where useful

Never skip tenancy enforcement.

----------------------------------------------------

# STEP 0 â€” VERIFY SKELETON

Confirm:

- Laravel app runs
- Authentication works
- Company creation flow works
- Default "General" department auto-created
- User assigned company_id + department_id
- Activity log system exists
- Sidebar layout working
- Dashboard shell loads

If anything missing â†’ fix before proceeding.

Create git checkpoint:
"checkpoint: skeleton stable"

STOP after completion and report.

----------------------------------------------------

# STEP 1 â€” VENDOR MANAGEMENT MODULE

Create vendor management system.

### Database
vendors table:
- id
- company_id
- name
- vendor_type
- contact_person
- phone
- email
- address
- bank_name
- account_name
- account_number
- notes
- is_active
- timestamps
- soft deletes

### Features
- Vendor list page (search + filter)
- Create vendor modal
- Edit vendor modal
- Vendor detail panel
- Store bank details
- Vendor payment insights panel:
  - total paid
  - payment count
  - last payment date
  - recent payments list
- Activity logs:
  vendor.created
  vendor.updated
  vendor.deleted

### Rules
- Company scoped only
- Only owner/finance can manage
- Manager/staff read only

STOP after completion and report.

----------------------------------------------------

# STEP 2 â€” ASSET MANAGEMENT MODULE

Create asset tracking system.

### Database

asset_categories:
- id
- company_id
- name
- code (system-generated)
- description
- is_active
- created_by
- updated_by
- timestamps

assets:
- id
- company_id
- asset_category_id
- asset_code
- name
- serial_number
- acquisition_date
- purchase_amount
- currency
- status (active|assigned|in_maintenance|disposed)
- condition
- notes
- assigned_to_user_id
- assigned_department_id
- assigned_at
- disposed_at
- disposal_reason
- salvage_amount
- maintenance_due_date
- warranty_expires_at
- created_by
- updated_by
- timestamps

asset_events:
- id
- company_id
- asset_id
- event_type (created|updated|assigned|transferred|returned|maintenance|disposed)
- event_date
- actor_user_id
- target_user_id
- target_department_id
- amount
- currency
- summary
- details
- metadata
- timestamps

company_asset_policy_settings:
- id
- company_id
- action_policies (json)
- metadata (json)
- created_by
- updated_by
- timestamps

asset_communication_logs:
- id
- company_id
- asset_id
- recipient_user_id
- event
- channel (in_app|email|sms)
- status (queued|sent|failed|skipped)
- recipient_email
- recipient_phone
- reminder_date
- dedupe_key
- message
- metadata
- sent_at
- read_at
- timestamps

### Features
- Asset list page
- Add/edit asset modal
- Create category modal
- Assign/transfer asset to staff
- Return asset
- Dispose asset
- Maintenance logs
- Custody/lifecycle history modal
- Bulk actions (assign/return/dispose)
- Asset reports page (filters, KPIs, CSV export)
- Asset controls policy page (owner-managed per-action role access)
- Reminder automation + retry operations + inbox integration

### Activity logs
asset.created  
asset.assigned  
asset.transferred  
asset.returned  
asset.maintenance.logged  
asset.disposed  
asset.updated  

STOP after completion and report.

----------------------------------------------------

# STEP 3 â€” REQUESTS & APPROVALS

Create spending request workflow.

### Database

requests:
- id
- company_id
- request_code
- title
- description
- department_id
- requested_by
- total_amount
- status
- created_at

request_items:
- request_id
- name
- qty
- unit_cost
- total

request_approvals:
- request_id
- step (manager/finance)
- action
- acted_by
- comment
- acted_at

### Workflow

Staff â†’ create request  
Manager â†’ approve  
Finance â†’ approve  
Then marked approved

### UI
- Requests list
- Create request modal
- Request detail panel
- Approve/reject buttons
- Approval timeline

### Activity logs
request.created  
request.approved  
request.rejected  

STOP after completion and report.

----------------------------------------------------

# STEP 4 â€” EXPENSES MODULE

Create expense recording system.

### Database

expenses:
- id
- company_id
- request_id optional
- department_id
- vendor_id
- title
- amount
- payment_method
- expense_date
- status (posted|void)
- is_direct
- created_by
- voided_by
- voided_at
- void_reason

expense_attachments:
- expense_id
- file_path
- original_name
- mime_type
- file_size
- uploaded_by

### Features
- Record expense (from approved request or direct)
- Upload receipts
- Vendor linked expenses
- Expense list page
- Expense detail panel
- Void flow with dedicated confirm modal and required reason
- Attachment secure download with authorization checks

### Activity logs
expense.created  
expense.updated  
expense.voided  
expense.attachment.uploaded  

STOP after completion and report.

----------------------------------------------------

# STEP 5 â€” DASHBOARD REAL DATA

Update dashboard with real data.

Show:
- total spend this month
- pending approvals
- total vendors
- asset count
- missing assets
- recent expenses

Use:
- loading skeletons
- lazy loading

STOP after completion and report.

----------------------------------------------------

# STEP 6 â€” HARDENING PASS

Before Phase 1 complete:

Verify:
- company scoping everywhere
- roles working correctly
- no cross-company data leaks
- loading states present
- validation present
- indexes added for performance

Create git commit:
"phase1 complete"

STOP.

---

## Addendum: Module Definition of Done
A step is complete only if all are true:
- create/edit/delete actions function end-to-end.
- field-level validation messages are visible and readable.
- tenancy (`company_id`) is enforced on read/write paths.
- role permissions are enforced server-side.
- activity logs are produced for all critical actions.
- loading states are present and no debug/fallback leftovers remain.

## Addendum: Expense Module Lock Notes (2026-02-17)
- Locked flow:
  - list + filters
  - create/edit
  - read-only view modal
  - separate void confirm modal
  - attachment download
- Required UX states verified:
  - open/view/void button loading labels
  - save/void disabled during processing
- Cleanup decisions:
  - removed temporary test modal/fallback patterns
  - kept destructive actions outside read-only view modal

## Addendum: Blueprint Parity Upgrade Sequence (After Baseline)
Apply these upgrades before adding net-new modules:

1) Expenses Depth Upgrade (first priority)
- enforce posted/void lifecycle strictly
- require `void_reason` and actor/timestamp capture
- support both direct and request-linked expense creation paths
- add stronger status filters and audit timeline visibility

2) Vendor Finance Upgrade (second priority)
- add vendor invoice model and UI
- record partial/full invoice payments
- compute outstanding balances in real time
- expose vendor statement timeline (invoices + payments)

3) Control and Visibility Upgrade
- budget threshold alerts
- reporting exports (CSV/PDF)
- in-app notifications + email events

## Addendum: Approved IA + UX Refactor Backlog (Owner Approved)
Objective:
- Split overloaded settings screens into clear operational modules.
- Improve scale-readiness with pagination and consistent table behavior.

Execution order:
1) Navigation and page split
- Create first-class pages for `Departments`, `Team`, and `Approval Workflows`.
- Keep `Settings` for platform/company configuration only.

2) Organization data UX cleanup
- Separate concerns per page:
  - Departments: CRUD + department head assignment
  - Team: staff CRUD + role assignment + reports-to hierarchy
  - Approval Workflows: workflow CRUD + default + step chain

3) Table standardization
- Add server-side pagination to all operational tables.
- Add row count footer and page-size selector (10/25/50).
- Keep search/filter/sort behavior consistent.

4) Engineering hardening
- Ensure indexed queries for search/filter fields.
- Keep heavy side effects (email/notification) async via queues.
- Guard against duplicate submits and concurrency issues.

Status:
- Approved and queued as the active refactor track before additional module expansion.

## Addendum: Requests & Approvals Next Phase Scope (Approved Execution List)
Objective:
- Build the full request lifecycle and approval operations on top of the already completed hierarchy + workflow configuration.

Feature list (must ship together for module completeness):
1) Request creation and draft management
- Create request with type, department, title, amount, and due date.
- Save as draft, edit draft, submit draft.
- Support line items and computed totals.

2) Request list and request detail
- Server-side pagination (10/25/50), search, and filters.
- Status filters and date filters.
- Detail view with full request metadata, line items, attachments, and approval progress.

3) Approval inbox
- "My Pending Approvals" queue for eligible approvers.
- Approve, reject, and return actions with required reason/comment where applicable.
- Clear pending badge/count and step context per row.

4) Workflow-driven routing
- Resolve workflow from selected workflow or company default.
- Resolve approvers from hierarchy-aware sources:
  - direct manager (reports-to)
  - department head/manager
  - fixed role owner (finance/owner/etc.)
  - specific user
- Advance request step deterministically on each decision.

5) Approval timeline and audit
- Append-only decision history:
  - who acted
  - action
  - comment
  - from/to state
  - timestamp
- Emit activity logs for all request and approval actions.

6) Policy checks and controls
- Budget guardrail checks at submit/approval transition points.
- Duplicate request warning signal (non-blocking at first pass).
- Role and company scope enforcement on every request/approval query and write.

7) Expense linkage contract
- Approved request can be converted/linked to expense record.
- Keep direct expense path available (request linkage optional).

8) UX and reliability standards
- Loading states for all actions.
- No double-submit behavior.
- Consistent toast feedback for success/error states.
- Modal and table behavior must match UI standards already established in Expenses/Team modules.

## Addendum: Request vs Expense Boundary (Authoritative)
- Requests are not only for expenses.
- Request module captures intent and authorization before money moves (purchase/payment/travel/expense request).
- Expense module records actual payment/spend execution and evidence.
- Linkage rule:
  - Controlled mode: request approved first, then expense recorded from request.
  - Direct mode: expense can be recorded directly by authorized finance roles.

## Addendum: Communications Rollout Plan (Email, SMS, In-App)
Do not block request/approval core logic on communication delivery.

Rollout order:
1) In-app notifications (start immediately after Requests + Approval inbox is stable)
- Trigger on submit, approve, reject, return, and final approval.
- Show unread counts and mark-as-read.

2) Email notifications (next pass, queue-backed)
- Same core events as in-app.
- Use queued jobs + retry policy + idempotency key per event.

3) SMS notifications (final pass, policy-driven)
- Use only for high-priority events by default (for example: final approval/rejection or overdue escalation).
- Must be tenant-configurable and template-controlled.

Readiness gates before enabling email/SMS:
- Request and approval state machine fully stable.
- Queue worker running in target environment.
- Notification templates approved.
- Organization-level communication preferences configured.

## Addendum: Multi-Tenant Attachment Storage Checkpoint
Current implemented storage model:
- Expense attachments are stored on local private disk under tenant-partitioned paths:
  - `private/expense-attachments/{company_id}/{expense_id}/...`
- Attachment rows are also company-scoped in database (`company_id` on `expense_attachments`).
- Download access is policy-gated and company-context scoped (`expenses.attachments.download`).

Decision kept:
- Keep single storage backend with strict tenant folder partition + DB scope + authorization checks.
- Do not create separate physical disk/bucket per tenant in current phase.

Implemented update:
- Request-level attachments are now implemented end-to-end in Requests module:
  - Upload in request draft/create-edit modal.
  - Upload in request detail modal for editable states (`draft`, `returned`).
  - Secure private storage path with tenant partition:
    - `private/request-attachments/{company_id}/{request_id}/...`
  - Secure download route with policy checks:
    - `requests.attachments.download`
  - Submission guard for request types that require attachments.

## Addendum: Requests & Approvals Remaining Work (Historical)
This section is historical reference only.
All items listed below have now been implemented in the closeout runs.
Requests/Approvals is complete for current phase scope.

Implemented update:
- Amount-range workflow enforcement is now active end-to-end:
  - Submit uses only workflow steps where request amount falls within step `min_amount`/`max_amount`.
  - Ineligible steps are not created for that request.
  - Current approval step is set to the first applicable step.
  - Approval progression only advances to the next applicable step (skips out-of-range future steps).
  - If no step applies to amount, submit is blocked with workflow validation error.

Implemented update:
- Request policy checks are now active:
  - Added company-level request policy settings:
    - budget guardrail mode: `off | warn | block`
    - duplicate detection toggle + lookback window days
  - Added request submit budget guardrail enforcement:
    - `block` mode: submit blocked when projected spend exceeds active department budget
    - `warn` mode: submit allowed with warning captured in request metadata + audit log
  - Added duplicate request detector at submit stage:
    - warning-only behavior (non-blocking)
    - warnings captured in request metadata + audit log
  - Request detail UI now shows policy warnings.

1) Request reporting filters and list hardening
- Add date-range filters for request list (submitted/requested date window).
- Add richer status+type analytics counters for operations view.

2) Approval operations UX polish
- Keep current channels-on-submit flow, but add clearer per-step delivery summary in timeline.
- Add explicit "why not approver" context in pending queue when role sees request but cannot act.

3) Expense handoff from approved request
- Add explicit "Create Expense from Approved Request" action path.
- Auto-carry allowed fields (department/vendor/line items/reference) with immutable link.

4) Communication delivery integration layer
- Keep request/approval transitions non-blocking.
- Implement queued dispatch pipeline for:
  - in-app (first)
  - email (second)
  - SMS (third)
- Keep organization-configured channel policy and request-time channel reduction rules.

## Addendum: Requests/Approvals Permission Hardening (Implemented)
Implemented in this cycle:

1) Entry authorization on Requests pages
- Added explicit policy gate checks on component mount for:
  - `RequestsPage`
  - `RequestCommunicationsPage`
  - `RequestReportsPage`
- Behavior:
  - inactive users are blocked with `403`
  - users without request access are blocked with `403`

2) Delivery logs least-privilege controls
- Delivery log visibility:
  - allowed roles: owner, finance, manager, auditor
- Delivery operation execution (retry/process):
  - allowed roles: owner, finance
- Staff:
  - cannot open Delivery Logs tab
  - cannot trigger retry/process actions
- Manager/Auditor:
  - can view delivery logs
  - cannot execute retry/process actions

3) UI hardening for communications page
- Delivery Logs tab is rendered only for roles allowed to view delivery logs.
- Server-side guard also enforces this (forced tab switch attempts are blocked).
- Retry/process buttons remain hidden unless execution permission is granted.

4) Automated test coverage added
- Route-level access:
  - active user access
  - inactive user `403` for all request routes
- Communications permissions:
  - staff denied delivery logs + denied retry operations
  - manager allowed view but denied retry operations
  - owner allowed retry operations
- Existing request hierarchy and automation tests remain green.

Latest verification:
- `php artisan test tests/Feature/Requests/RequestApprovalAutomationTest.php tests/Feature/Requests/RequestApprovalHierarchyTest.php`
- Result: `15 passed` / `0 failed`

## Addendum: Requests/Approvals Completion Run (Implemented)
Implemented in this run:

1) Request list/report hardening
- Added date-range filters (`From`, `To`) to Requests list using submitted/created date window logic.
- Added request operations analytics on Requests page:
  - total requests
  - filtered total amount
  - pending my action
  - status breakdown counters
  - request type breakdown counters
- Kept server-side pagination and filter loading states aligned with existing UI behavior.

2) Approval operations UX polish
- Added per-row approval context in Requests table for in-review records:
  - explicit "awaiting your decision" when user is current approver
  - explicit current step + approver names when user is view-only for that step
- Added per-step delivery summary in approval timeline:
  - sent/queued/failed/skipped counts
  - channel summary per step
- Added explicit in-modal approval context panel for in-review requests when viewer cannot decide.

3) Expense handoff from approved request
- Added direct action in Request detail modal: `Create Expense`.
- Enforced conversion rules:
  - only approved requests
  - only roles allowed to create expenses
  - one expense linkage per request (prevents duplicate conversion)
- Auto-carried fields into expense creation:
  - request link (`request_id`, immutable)
  - department
  - vendor (request-level, else first line-item vendor fallback)
  - title
  - description
  - amount (approved amount fallback to request amount)
  - payment actor (`paid_by_user_id` from current user)
- Request detail now shows linked expense summary after conversion.

4) Communication delivery integration (queue-backed dispatch)
- Introduced queued delivery job:
  - `app/Jobs/ProcessRequestCommunicationLog.php`
- Updated communication logger to enqueue delivery processing per log instead of direct inline delivery:
  - `RequestCommunicationLogger` now dispatches background processing
- Existing retry/failure center remains active and compatible with queued logs.

Verification:
- `php artisan test tests/Feature/Requests/RequestApprovalAutomationTest.php tests/Feature/Requests/RequestApprovalHierarchyTest.php`
- Result: `24 passed` / `0 failed`

## Addendum: Requests/Approvals Closeout Patch (Implemented)
Implemented in this run:

1) Reports average-time safety fix
- Fixed negative average decision time display in Request Reports.
- Rule applied: average decision time is clamped to a minimum of `0.0h` to handle bad legacy timestamp order (`decided_at < submitted_at`).
- File:
  - `app/Livewire/Requests/RequestReportsPage.php`

2) Regression protection
- Added automated test to ensure request reports never show negative average decision hours.
- File:
  - `tests/Feature/Requests/RequestApprovalAutomationTest.php`

Verification:
- `php artisan test tests/Feature/Requests`
- Result: `25 passed` / `0 failed`
- `php artisan view:cache` passed

Module status:
- Requests/Approvals module is closed for current phase execution scope.
- Any next work on this area moves to enhancement scope (not blocker/fix scope).

## Addendum: Expense Permission Matrix (Implemented)
Implemented in this run:

1) Company-configurable expense action controls
- Added company-scoped expense policy settings model:
  - `company_expense_policy_settings`
- Added per-action policy controls:
  - `create_direct_expense`
  - `create_expense_from_request`
  - `edit_posted_expense`
  - `void_expense`
- Each action supports:
  - allowed roles
  - optional department scope
  - optional role-level amount limits
  - optional secondary-approval requirement when limit is exceeded

2) Resolver and enforcement
- Added `ExpensePolicyResolver` as single source of truth.
- Wired policy checks into:
  - `ExpensePolicy` (view/create/update/void/upload)
  - `CreateExpense`
  - `UpdateExpense`
  - `VoidExpense`
  - request->expense conversion path in `RequestsPage`

3) Settings UI
- Added `Settings > Expense Controls` page for admin (owner).
- Added form controls to manage action-level role matrix and optional limits/scopes.
- Added reset-to-default action (Owner + Finance baseline).

4) Navigation and UX alignment
- Added route: `settings.expense-controls`.
- Added Settings card and owner sidebar link.
- Updated expense page permission messaging to reflect configurable policy.

5) Compatibility behavior
- Default policy preserves prior baseline:
  - Owner + Finance allowed by default.
- Organizations can now enable Manager/Staff expense actions per policy.

6) Validation hardening (implemented)
- Expense control save now blocks misconfiguration where:
  - amount limit is entered for a role that is not in allowed roles.
- Inline section error is shown on the action card until corrected.

7) UI access and navigation notes
- Owner sidebar now supports vertical scroll for long navigation stacks.
- `Expense Controls` appears under owner settings navigation.

## Addendum: Vendor Finance Enhancement + Configuration Usage Guide (Implemented)
Implemented in this run:

1) Vendor invoice + payment data model
- Added:
  - `vendor_invoices`
  - `vendor_invoice_payments`
- Supports:
  - invoice statuses (`unpaid`, `part_paid`, `paid`, `void`)
  - partial and full settlement
  - outstanding balance tracking per invoice
  - company-scoped uniqueness (`invoice_number`, `payment_reference`)

2) Vendor finance actions
- Added actions:
  - `CreateVendorInvoice`
  - `UpdateVendorInvoice`
  - `RecordVendorInvoicePayment`
  - `VoidVendorInvoice`
- Guardrails:
  - cannot overpay outstanding amount
  - cannot pay void invoice
  - cannot update void invoice
  - cannot reduce total below already paid amount

3) Vendor detail UI upgrade
- Vendor detail panel now includes:
  - invoice ledger cards (invoiced, paid, outstanding, counts)
  - invoice search + status filtering
  - invoice action controls (create/edit/record payment/void)
  - statement timeline (invoice + payment events)
- Added finance modals:
  - Create/Edit Invoice
  - Record Payment
  - Void Invoice

4) Configuration usage across Flowdesk (how modules connect)
- `Team` page:
  - assign staff role + department + reports-to hierarchy.
- `Approval Workflows` page:
  - defines who approves requests and in what order.
- `Communications` page:
  - defines default channels for request/approval events.
- `Expense Controls` page:
  - defines who can create direct expense vs request-linked expense, and limit behavior.
- `Vendor Controls` page:
  - defines per-action role access for vendor module operations (directory, profile CRUD, invoices, payments, exports, communications).
- `Vendors` page:
  - manages vendor registry + invoice/payment ledger and balances.

5) Practical setup sequence for a new organization
1. Configure departments and team hierarchy.
2. Configure approval workflows and set default workflow.
3. Configure communications defaults (in-app/email/SMS policy).
4. Configure expense controls (direct vs request-based permissions).
5. Create vendors and start invoice/payment ledger operations.

6) Scope note
- Vendor policy matrix is now implemented via `company_vendor_policy_settings` and `Vendor Controls`:
  - view directory
  - create/update/delete vendor
  - manage invoices
  - record payments
  - export statements
  - manage communications

7) Vendor finance attachment extension (implemented)
- Added invoice and payment proof storage:
  - `vendor_invoice_attachments`
  - `vendor_invoice_payment_attachments`
- Added upload/download flows:
  - invoice attachments at invoice create/edit
  - payment proof attachments at payment record
  - secure download endpoints with vendor view authorization
- Added vendor profile UI visibility:
  - invoice-level attachment chips and file links
  - payment proof links in statement timeline
  - invoice cards now show `Invoice files` and `Payment proofs` counts

8) Vendor AP status polish (implemented)
- Added computed display status `overdue` (without breaking stored status model).
- Added due-date countdown labels:
  - `Due in X days`
  - `Due tomorrow`
  - `Due today`
  - `Overdue by X days`
- Added overdue count chip and overdue badge styling in invoice ledger.

9) Vendor traceability reports (implemented)
- Added dedicated page: `vendors.reports` (`/vendors/reports`).
- Purpose:
  - track vendor-linked vs vendor-unlinked expenses,
  - track request-linked vs request-unlinked expenses,
  - expose full request-expense-vendor trace path per record.
- Added report filters:
  - search (expense code/title/vendor/request code),
  - vendor link status,
  - request link status,
  - expense status,
  - department,
  - vendor,
  - date range and rows-per-page.
- Added summary cards:
  - total expenses,
  - vendor linked,
  - vendor unlinked,
  - request linked,
  - fully linked (request + expense + vendor).
- Added paginated table with trace labels:
  - `Request -> Expense -> Vendor`
  - `Request -> Expense (No Vendor)`
  - `Direct Expense -> Vendor`
  - `Direct Expense`.

10) Vendor communications delivery + retry center (implemented)
- Delivery pipeline:
  - logs are queued and processed asynchronously for in-app/email channels.
  - failed and stuck queued events can be retried safely.
- Internal AP reminder policy:
  - due/overdue reminders are internal-only by default (finance team inbox/email).
  - vendor external reminders are reserved for payment event notifications.
- Added vendor-level retry center:
  - failed count,
  - stuck queued count,
  - `older than` threshold,
  - actions: `Retry Failed` and `Process Queued`.
- Added audience clarity in logs:
  - each row shows whether delivery target is `Internal Finance` or `Vendor External`.
- Added company/vendor scoped console commands:
  - `vendors:communications:retry-failed --company= --vendor= --batch=`
  - `vendors:communications:process-queued --company= --vendor= --older-than= --batch=`

11) Vendor reporting depth extension (implemented)
- Added amount-range filters in vendor reports (`amount_min`, `amount_max`).
- Added AP aging cards:
  - outstanding invoices,
  - overdue 0-30,
  - overdue 31-60,
  - overdue 61+.
- Added quick navigation actions in reports table:
  - `Open in Expenses` deep links expense context,
  - `Open in Requests` for request-linked expense rows.

12) Asset module Step 1 core workflow (implemented)
- Added foundational data model:
  - `asset_categories` (company-scoped category register)
  - `assets` (asset profile + current custody + lifecycle fields)
  - `asset_events` (append-style history for created/updated/assigned/transferred/maintenance/disposed)
- Added asset actions:
  - `CreateAsset`
  - `UpdateAsset`
  - `CreateAssetCategory`
  - `AssignAsset`
  - `RecordAssetMaintenance`
  - `DisposeAsset`
- Added policy actions for lifecycle controls:
  - `assign`
  - `logMaintenance`
  - `dispose`
- Added UI module:
  - `Assets` page with filters + pagination
  - register/edit asset modal
  - category create modal
  - assign/transfer modal
  - maintenance log modal
  - disposal modal
  - custody/lifecycle history modal
- Added dashboard metric integration:
  - total assets
  - assigned assets
  - disposed assets

13) Asset module Step 2 (implemented) - category-code hardening + return workflow
- Category code creation is now fully system controlled:
  - `Create Category` modal does not accept manual category code input.
  - Manual code input is blocked server-side (`code` prohibited).
  - Saved code is generated from category name with company-scoped uniqueness.
- Added return-to-inventory lifecycle flow:
  - New action: `ReturnAsset`
  - New event type: `asset_events.event_type = returned`
  - Assets UI now includes `Return` action for assigned, non-disposed assets.
  - Added return modal (`event_date`, `summary`, `details`).
  - Return operation clears assignee/department assignment and sets status back to `active`.
- Test coverage extended:
  - `manager can return assigned asset to inventory`
  - `asset category code is auto generated and manual code is rejected`

14) Asset Controls policy layer (implemented)
- Added tenant-scoped settings model + table:
  - `company_asset_policy_settings`
  - `App\Domains\Assets\Models\CompanyAssetPolicySetting`
- Added runtime resolver:
  - `App\Services\AssetPolicyResolver`
- Wired `AssetPolicy` to resolver-backed controls:
  - `viewAny`
  - `view`
  - `create`
  - `update`
  - `assign`
  - `logMaintenance`
  - `dispose`
- Added owner-only settings UI:
  - `Settings > Asset Controls`
  - page class: `App\Livewire\Settings\AssetControlsPage`
  - view: `resources/views/livewire/settings/asset-controls-page.blade.php`
- Added app navigation and settings links:
  - owner sidebar entry: `Asset Controls`
  - settings landing page card: `Asset Controls`
- Added coverage test:
  - `asset controls can restrict assignment by role`

15) Asset Reports page (implemented)
- Added dedicated route and page:
  - `GET /assets/reports`
  - Livewire: `App\Livewire\Assets\AssetReportsPage`
- Added reports navigation:
  - `Assets` page now includes an `Asset Reports` button.
  - Reports page includes `Back to Assets` action.
- Added report capabilities:
  - filters: search, status, category, assignee, department, acquisition date range
  - KPI cards: total assets, assigned, unassigned, maintenance cost, disposed
  - paginated report table with maintenance totals per asset
  - CSV export for current filtered report scope

16) Asset reminder automation + communications integration (implemented)
- Added reminder source fields on assets:
  - `maintenance_due_date`
  - `warranty_expires_at`
- Added internal asset communication log pipeline:
  - table/model: `asset_communication_logs` / `AssetCommunicationLog`
  - queue job: `ProcessAssetCommunicationLog`
  - delivery manager: `AssetCommunicationDeliveryManager`
  - retry service: `AssetCommunicationRetryService`
  - reminder dispatcher: `AssetReminderService`
- Added scheduled/operational commands:
  - `assets:reminders:dispatch`
  - `assets:communications:retry-failed`
  - `assets:communications:process-queued`
- Added shared inbox integration:
  - `Request Communications` inbox now includes `Assets` notifications (source-tagged)
  - mark-read and mark-all-read now include asset notification rows
- Added tests:
  - `AssetReminderServiceTest` (queue + dedupe behavior)

17) Asset bulk operations (implemented)
- Added asset registry bulk selection flow:
  - row checkboxes
  - select-all for current visible page
  - selected-count bulk toolbar
- Added bulk operations modal with one unified workflow for:
  - bulk assign/transfer
  - bulk return to inventory
  - bulk dispose
- Bulk operations are executed per asset through existing action classes:
  - `AssignAsset`
  - `ReturnAsset`
  - `DisposeAsset`
- Added partial-success handling:
  - processed vs failed counts in feedback
  - failed asset IDs remain selected for retry
- Added test coverage:
  - `manager can bulk assign assets from assets page`

18) Asset usage flow (implemented)
- Register:
  - Open `Assets` page -> `Register Asset`.
  - Select category and complete asset profile.
  - Save creates `asset_events.created`.
- Assign/transfer:
  - Use row action `Assign`/`Transfer`.
  - Assignee department auto-populates from selected assignee profile.
  - Save creates `asset_events.assigned` or `asset_events.transferred`.
  - Assignment communication logs are queued for assignee on enabled channels.
- Return:
  - Use row action `Return` for assigned assets.
  - Save creates `asset_events.returned` and clears current custody fields.
- Maintenance:
  - Use row action `Maintenance`.
  - Save creates `asset_events.maintenance` and records optional cost/currency.
- Dispose:
  - Use row action `Dispose`.
  - Save creates `asset_events.disposed` and locks assignment lifecycle actions.
- Bulk operations:
  - Select rows -> `Bulk Assign` / `Bulk Return` / `Bulk Dispose`.
  - System executes each selected asset through existing action classes and reports partial successes.
- Reporting:
  - `Assets -> Asset Reports` provides KPI cards, filters, pagination, and CSV export.
- Operations/automation:
  - `php artisan assets:reminders:dispatch --company= --days-ahead=7`
  - `php artisan assets:communications:retry-failed --company= --batch=200`
  - `php artisan assets:communications:process-queued --company= --older-than=2 --batch=500`

19) Approval timing controls (implemented)
- Added dedicated settings module: `Settings -> Approval Timing Controls`
- Added organization-level default timing configuration:
  - step due hours
  - reminder hours before due
  - escalation grace hours
- Added department-level timing overrides with explicit remove-to-inherit behavior.
- Added policy resolver service:
  - `App\Services\ApprovalTimingPolicyResolver`
  - precedence: step metadata -> department override -> org default -> config fallback
- Integrated resolver into request approval lifecycle:
  - submit sets first pending step `due_at` using resolved policy
  - approve-to-next-step sets next pending step `due_at` using resolved policy
  - SLA processor backfills missing `metadata.sla` and `due_at` from resolved policy
- Added policy storage tables:
  - `company_approval_timing_settings`
  - `department_approval_timing_overrides`
- Added tests:
  - owner/staff access checks for timing settings
  - precedence validation (department override over org default)
  - request submit timing application validation (`due_at` and metadata snapshot)
