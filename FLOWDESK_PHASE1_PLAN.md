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

# STEP 0 — VERIFY SKELETON

Confirm:

- Laravel app runs
- Authentication works
- Company creation flow works
- Default "General" department auto-created
- User assigned company_id + department_id
- Activity log system exists
- Sidebar layout working
- Dashboard shell loads

If anything missing → fix before proceeding.

Create git checkpoint:
"checkpoint: skeleton stable"

STOP after completion and report.

----------------------------------------------------

# STEP 1 — VENDOR MANAGEMENT MODULE

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

# STEP 2 — ASSET MANAGEMENT MODULE

Create asset tracking system.

### Database

assets:
- id
- company_id
- asset_code
- name
- type
- brand
- serial_number
- purchase_cost
- purchase_date
- vendor_id
- status
- condition
- location
- notes
- timestamps

asset_assignments:
- asset_id
- assigned_to_user_id
- assigned_by
- assigned_at
- returned_at
- status
- notes

asset_maintenance_logs:
- asset_id
- description
- cost
- vendor_id
- date
- status

### Features
- Asset list page
- Add asset modal
- Edit asset
- Assign asset to staff
- Return asset
- Mark lost/damaged
- Maintenance logs
- Asset detail panel

### Activity logs
asset.created  
asset.assigned  
asset.returned  
asset.updated  

STOP after completion and report.

----------------------------------------------------

# STEP 3 — REQUESTS & APPROVALS

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

Staff → create request  
Manager → approve  
Finance → approve  
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

# STEP 4 — EXPENSES MODULE

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

# STEP 5 — DASHBOARD REAL DATA

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

# STEP 6 — HARDENING PASS

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

Open item:
- Request-level attachments are not yet implemented end-to-end in Requests module.

## Addendum: Requests & Approvals Remaining Work (Approval Queue)
The Requests/Approvals module is functional but not complete yet. Remaining items to approve and execute:

1) Request attachments (complete end-to-end)
- Upload in request draft/edit.
- Secure private storage path with tenant partition:
  - `private/request-attachments/{company_id}/{request_id}/...`
- Download/view endpoint with policy checks.
- Show attachments in request detail and approval review.

2) Amount-range workflow enforcement
- Enforce `min_amount` / `max_amount` on workflow steps during routing and approver eligibility.
- Ensure only matching steps are created/advanced for a given request amount.

3) Request policy checks (parity with expense controls)
- Add request-stage budget guardrail checks (warn/block rules by company policy).
- Add duplicate request detector (initially warning/non-blocking, logged in audit).

4) Request reporting filters and list hardening
- Add date-range filters for request list (submitted/requested date window).
- Add richer status+type analytics counters for operations view.

5) Approval operations UX polish
- Keep current channels-on-submit flow, but add clearer per-step delivery summary in timeline.
- Add explicit "why not approver" context in pending queue when role sees request but cannot act.

6) Expense handoff from approved request
- Add explicit "Create Expense from Approved Request" action path.
- Auto-carry allowed fields (department/vendor/line items/reference) with immutable link.

7) Communication delivery integration layer
- Keep request/approval transitions non-blocking.
- Implement queued dispatch pipeline for:
  - in-app (first)
  - email (second)
  - SMS (third)
- Keep organization-configured channel policy and request-time channel reduction rules.
