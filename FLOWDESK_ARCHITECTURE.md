# FLOWDESK_ARCHITECTURE.md
Flowdesk is a modern fintech-style corporate control platform for managing **spending approvals** and **asset management** across many companies (multi-tenant SaaS).

---

## 1) Product Scope (Stage 1)
Stage 1 ships two pillars:

### A. Spending & Approvals
- Requests (purchase/payment/expense/travel cash request)
- Multi-step approvals (Staff → Manager → Finance)
- Vendor management
- Department budgets
- Expense tracking + receipt capture
- Dashboards + basic reporting
- Expense lifecycle control (posted, voided, and auditable reason)
- Vendor finance intelligence (paid totals, last payment date, payment count)
- Reporting/export readiness (CSV/PDF in reporting domain)

### B. Asset Management
- Asset register (laptops, vehicles, equipment, tools)
- Category-based asset register with system-generated category codes
- Assign/transfer assets to staff
- Return-to-inventory flow
- Transfer history + append-only event logs
- Maintenance logs
- Disposal flow with salvage/disposal reason tracking
- Maintenance/warranty due reminders with queue + retry operations
- Asset controls policy layer (per-action role access)

Non-goals for Stage 1:
- Virtual cards, wallets, payroll, deep ERP modules (these come later)

---

## 2) Tenancy (Multi-Company SaaS)
Flowdesk is built as a **multi-tenant SaaS**.

### Tenancy model (v1)
- Single database
- Most business tables include `company_id`
- Every query is scoped by `company_id`

### Hard rules
1. Any “company-owned” record MUST include `company_id`.
2. No user can access a record that belongs to another company.
3. All list queries must be scoped: `->where('company_id', auth()->user()->company_id)`.
4. If a record is created, it MUST be saved with `company_id` from the authenticated user.
5. Admin-like views still remain scoped to the user’s company unless explicitly marked global.

Global/shared tables (no company_id):
- Countries/States metadata (optional)
- System enums/config tables (optional)

---

## 3) Roles (Stage 1)
We keep roles simple and practical.

### Roles
- Owner (Company Admin)
- Finance
- Manager
- Staff
- Auditor (Read-only) [optional but supported]

### Role responsibilities
Owner:
- Full access within company
- Manage company settings, departments, users
- View all spend and assets

Finance:
- Approve finance stage requests
- Manage vendors and budgets
- View all spend and assets
- Mark expenses as reconciled (optional v1)

Manager:
- Approve manager stage requests for their department/team
- View department activity (or company-wide depending on configuration)

Staff:
- Create requests
- View own requests and related approvals
- View assets assigned to them

Auditor:
- Read-only access to dashboards, requests, expenses, assets

---

## 4) Core Modules (Domain Boundaries)
Flowdesk is built in “domains” so it scales without becoming messy.

### Domains (Stage 1)
- Company (tenancy, departments, settings)
- Identity (users, roles)
- Requests (requests, request items, status)
- Approvals (approval workflow, approval logs)
- Vendors (vendor records)
- Budgets (department budgets)
- Expenses (expense entries, receipts)
- Assets (asset_categories, assets, asset_events, company_asset_policy_settings, asset_communication_logs)
- Dashboard (metrics, cards, charts)
- Reports (operational and financial analytics)
- Notifications (in-app + email alerts)
- Audit (activity logs)

---

## 5) Navigation (Stage 1)
Sidebar:
- Dashboard
- Requests & Approvals
- Expenses
- Vendors
- Budgets
- Assets
- Reports
- Departments
- Team
- Approval Workflows
- Settings

Settings (Owner/Finance):
- Company profile
- Access and policy configuration
- Integrations and system preferences
- Company profile and billing

Navigation rule:
- Operational modules (Departments, Team, Approval Workflows) are first-class pages.
- Settings is reserved for configuration, not day-to-day operations.

---

## 6) UI/UX Principles (Stripe + Notion + Brex style)
- Minimal, clean, white space
- Card-driven dashboard
- Slide-over panels (right side) for details, approve/reject, edit
- Tables like Notion (clean, sortable)
- No heavy “admin panel” look
- Clear statuses with subtle badges:
  - Pending (orange)
  - Approved (green)
  - Rejected (red)
  - Draft (gray)

---

## 7) Status System (Stage 1)
### Request status (canonical)
- draft
- pending_manager
- pending_finance
- approved
- rejected
- cancelled

### Expense status (canonical)
- posted
- void

### Vendor invoice status (Stage 1.5/2)
- unpaid
- part_paid
- paid
- void

### Asset status (canonical)
- active
- assigned
- in_maintenance
- disposed

---

## 8) Approval Workflow (Stage 1)
Default workflow:
1. Staff creates request
2. Manager approves/rejects
3. Finance approves/rejects
4. Approved request can generate an expense entry (optional linkage)

Approval tracking must be auditable:
- who approved
- when
- comment
- previous status transition

### 8.1 Expense workflow modes (Stage 1 baseline + upgrade path)
1. Direct expense mode
   - Finance records spending directly.
   - Expense is saved as `posted` with complete audit metadata.
2. Request-linked expense mode
   - Approved request is converted into expense record.
   - Request and expense stay linked for traceability.
3. Void flow (controlled reversal)
   - Only authorized roles can void.
   - Void requires explicit reason.
   - Previous values remain auditable.

### 8.2 Requests & Approvals operational scope (current build phase)
Requests module responsibilities:
- Capture spending intent before spend execution.
- Support request types beyond expense only (purchase/payment/travel/expense request).
- Manage draft -> submitted -> review -> decision lifecycle.

Approvals module responsibilities:
- Route each submitted request through configured workflow steps.
- Resolve approvers from organization hierarchy and policy configuration.
- Maintain deterministic step progression and append-only decision trail.

Core decision actions:
- approve
- reject
- return_for_update

Implementation rule:
- Expense recording remains a separate execution module.
- Approved requests may be linked to expenses, but request approval and expense posting are distinct lifecycle stages.

### 8.3 Approval Timing Controls (implemented)
Flowdesk applies approval response deadlines per step using a policy precedence model:
1. Step-level timing snapshot in approval metadata (if present)
2. Department timing override
3. Organization timing default
4. Config fallback

Timing values:
- `step_due_hours`: response deadline window for a pending step
- `reminder_hours_before_due`: reminder trigger before step deadline
- `escalation_grace_hours`: escalation trigger after deadline

When a step becomes `pending`, Flowdesk sets:
- `due_at`
- `metadata.sla` snapshot used for that step lifecycle

Background automation (SLA processor):
- Sends reminder at reminder threshold
- Sends escalation after grace threshold
- Writes `reminder_sent_at`, `reminder_count`, `escalated_at`
- Logs communication events without blocking request state transitions

UI/operations:
- Settings -> Approval Timing Controls (owner-managed)
- Organization defaults + per-department overrides
- Request Details modal timeline shows `Due`, `Reminder sent`, `Escalated`

---

## 9) Logging / Audit (Mandatory)
Every important action must be logged:
- Created request
- Edited request
- Approved / rejected
- Created vendor
- Assigned asset
- Returned asset
- Logged asset maintenance
- Disposed asset

Audit logs must include:
- company_id
- user_id
- action
- entity type
- entity id
- metadata (json)
- timestamp

---

## 10) Folder Structure (Required)
We build with clean domain boundaries.

Recommended structure:
app/
  Domains/
    Company/
    Identity/
    Requests/
    Approvals/
    Expenses/
    Vendors/
    Budgets/
    Assets/
    Dashboard/
    Audit/
  Livewire/
    Dashboard/
    Requests/
    Expenses/
    Vendors/
    Budgets/
    Assets/
    Settings/
  Actions/
  Services/
  Policies/
  Enums/

Rules:
- `Domains/*` contains business models + domain services
- `Livewire/*` contains UI state + forms + tables + modals
- Complex “do one thing” operations go into `Actions/*`
- Cross-domain utilities go into `Services/*`

Views:
resources/views/app/
  dashboard/
  requests/
  expenses/
  vendors/
  budgets/
  assets/
  settings/

Routes:
- All routes remain in `routes/web.php` (per your preference)
- Use route groups + naming:
  - requests.*
  - assets.*
  - vendors.*
  - budgets.*
  - dashboard.*

---

## 11) Security Rules (Non-negotiable)
- Every query must be scoped by company_id (unless global metadata)
- Authorization checks must run for:
  - viewing a request
  - approving a request
  - editing an asset
  - assigning an asset
- Never trust front-end role checks; always enforce on server

## 11.1 Performance and Concurrency Rules (Non-negotiable)
- Large tables must use server-side pagination by default (10/25/50 per page).
- Search and filters must execute on indexed fields.
- Avoid loading full datasets in Livewire components for operational tables.
- Critical multi-step writes must run in transactions.
- Queue email/notification side effects (no blocking HTTP request paths).
- Use idempotent handlers for retryable operations.
- Add throttling on expensive or high-frequency endpoints/actions.

---

## 12) Stage Roadmap
### Stage 1 (now)
- Requests + approvals
- Vendors + budgets
- Expenses + receipts
- Assets + lifecycle events + controls + reminders
- Dashboard + audit logs

### Stage 2 (next)
- Vendor invoices, payments, and outstanding balance tracking
- Budget threshold alerts and policy checks at request/expense stages
- In-app notifications and email alert events
- Better reporting + exports (CSV/PDF)

### 12.2 Communication channel rollout contract
Order of enablement:
1. In-app notifications first (non-blocking, immediate UX value)
2. Email second (queue-backed, retriable, idempotent)
3. SMS third (high-priority events only, policy-controlled)

Non-negotiable constraints:
- Communication failures must never break approval state transitions.
- All channel sends must execute asynchronously via queues.
- Tenant-level communication preferences must be respected.

### Stage 3 (Payments Rails Integration)
- Wallet + virtual cards + transaction feeds
- Limits, categories, policy enforcement
- Integrations

### 12.1 Current implementation status (as of February 21, 2026)
- Implemented and usable:
  - Vendors module (CRUD + payment insights from expenses)
  - Expenses module (create/edit/view, attachments, void flow, filters)
- Routed but still placeholder pages:
  - Requests and Approvals
  - Budgets
  - Assets
  - Reports

---

## 13) What Codex MUST do
When generating code:
- Follow this architecture strictly
- Do not invent random folders
- Always include company_id scoping
- Use Actions for core business operations
- Ensure audit logs for important actions
- Keep UI modern and minimal

---

## Addendum: Local XAMPP Subfolder Note
When running from a subfolder URL (for example `http://localhost/flowdesk/public`), Livewire script/update paths must be path-aware.
Preferred production and long-term local shape: use a virtual host/domain with document root pointing to `/public` to avoid subfolder routing overrides.


