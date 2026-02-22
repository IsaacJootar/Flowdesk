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
