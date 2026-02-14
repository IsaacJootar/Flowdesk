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

### B. Asset Management
- Asset register (laptops, vehicles, equipment, tools)
- Assign assets to staff
- Transfer history + activity logs
- Maintenance logs
- Return checklist (offboarding)
- Missing/overdue asset alerts

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
- Assets (assets, assignments, maintenance, returns)
- Dashboard (metrics, cards, charts)
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
- Settings

Settings (Owner/Finance):
- Company profile
- Departments
- Users
- Roles (simple assignment)
- Approval workflow settings (v2+)

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

### Asset status (canonical)
- available
- assigned
- under_repair
- retired
- lost

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
- previous status → new status

---

## 9) Logging / Audit (Mandatory)
Every important action must be logged:
- Created request
- Edited request
- Approved / rejected
- Created vendor
- Assigned asset
- Returned asset
- Marked asset lost / repaired

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

---

## 12) Stage Roadmap
### Stage 1 (now)
- Requests + approvals
- Vendors + budgets
- Expenses + receipts
- Assets + assignments + returns
- Dashboard + audit logs

### Stage 2 (next)
- Travel requests specialization (same request table, new types)
- Reimbursements and expense policies
- Better reporting + exports

### Stage 3 (fintech)
- Wallet + virtual cards + transaction feeds
- Limits, categories, policy enforcement
- Integrations

---

## 13) What Codex MUST do
When generating code:
- Follow this architecture strictly
- Do not invent random folders
- Always include company_id scoping
- Use Actions for core business operations
- Ensure audit logs for important actions
- Keep UI modern and minimal
