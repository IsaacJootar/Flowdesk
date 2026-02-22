# FLOWDESK_CODING_RULES.md
These rules are mandatory. Codex (and humans) must follow them to keep Flowdesk clean, scalable, and multi-tenant safe.

---

## 1) Project Structure (Non-negotiable)

### 1.1 Domain-first organization
Business logic lives in `app/Domains/*`.
UI logic lives in `app/Livewire/*`.

Required folders:
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
  Traits/

### 1.2 Views
All UI views under:
resources/views/app/
  dashboard/
  requests/
  expenses/
  vendors/
  budgets/
  assets/
  settings/

### 1.3 Routes
All routes remain in `routes/web.php`.
Use route groups and names:
- dashboard.*
- requests.*
- expenses.*
- vendors.*
- budgets.*
- assets.*
- settings.*

---

## 2) Tenancy Rules (Company Scoping)

### 2.1 Required
- Every company-owned record must have `company_id`.
- Every query must scope to the authenticated user company.

### 2.2 Implementation Standard
Create a reusable trait:
- `app/Traits/CompanyScoped.php`

Rules:
- On create: set `company_id = auth()->user()->company_id`
- On read/list: always add `->where('company_id', auth()->user()->company_id)`

### 2.3 Forbidden
- Never query records without `company_id` scope.
- Never accept `company_id` from request input.
- Never allow cross-company joins without scoping.

---

## 3) Departments Are Mandatory

### 3.1 Must-have rule
- Every user must have `department_id` (no null)
- Every request must have `department_id` (no null)
- Every budget must have `department_id` (no null)

### 3.2 Fallback
When creating a company, auto-create a default department:
- "General"
Then every staff belongs to a department (at minimum, General).

---

## 4) Roles and Authorization

### 4.1 Roles
Role field on `users.role`:
- owner
- finance
- manager
- staff
- auditor

### 4.2 Authorization
Use Policies for all sensitive models:
- RequestPolicy
- ExpensePolicy
- VendorPolicy
- BudgetPolicy
- AssetPolicy

Rules:
- Staff can only view/edit their own requests (unless owner/finance)
- Manager can view/approve requests in their department
- Finance can view/approve all company requests
- Auditor is read-only

---

## 5) Actions Pattern (Clean Business Logic)

### 5.1 Mandatory
All “do one thing” operations go into `app/Actions/*`.

Examples:
- `CreateRequest`
- `UpdateRequest`
- `SubmitRequest`
- `ApproveRequestAsManager`
- `ApproveRequestAsFinance`
- `RejectRequest`
- `CreateVendor`
- `AssignAsset`
- `ReturnAsset`
- `LogMaintenance`

Rules:
- Livewire components must NOT contain heavy logic.
- Livewire calls Actions.
- Actions validate server-side and enforce authorization.

---

## 6) Audit Logging (Mandatory)

### 6.1 Activity Logs
Use a single service:
- `app/Services/ActivityLogger.php`

Always log actions:
- request.created
- request.updated
- request.submitted
- request.approved.manager
- request.approved.finance
- request.rejected
- vendor.created/updated
- asset.created/updated
- asset.assigned
- asset.returned
- asset.maintenance.logged

Log payload should include:
- company_id
- user_id
- action
- entity_type
- entity_id
- metadata (json)

---

## 7) Livewire Conventions (Modern SaaS UI)

### 7.1 Component naming
Use descriptive names:
- `RequestsTable`
- `RequestFormModal`
- `RequestShowPanel`
- `AssetsTable`
- `AssetFormModal`
- `AssetAssignPanel`

### 7.2 UI behavior
- Prefer slide-over panels for view/edit/approve
- Prefer modals for create/edit (lightweight)
- Use card-based dashboards
- Use tables with filters/search

### 7.3 Validation
- Validate in Actions primarily
- Livewire may do basic validation for immediate UX but Action is final authority

---

## 8) Model Rules

### 8.1 Enums
Use `app/Enums/*` for statuses and types:
- RequestStatus
- RequestType
- AssetStatus
- AssetType
- ExpenseStatus

### 8.2 Money fields
All money stored as integer kobo:
- amount, unit_cost, total_amount, etc.

---

## 9) Database/Migrations Rules
- Every migration must include indexes for:
  - company_id
  - status fields
  - foreign keys used in filtering
- Use soft deletes for core business tables
- Avoid cascade deletes on financial records

---

## 10) Coding Style Rules
- Keep controllers thin (or avoid controllers for Livewire pages)
- Use Form Requests only where helpful; Actions can validate via Validator
- Never duplicate logic across components—use Actions/Services
- Keep naming consistent: Request, Expense, Vendor, Asset
- Form submit loading states and other loading states where neccessary. 
- Lazy loading on pages where neccessary. 

---

## 11) Testing (Optional for v1, recommended)
- Add basic Feature tests for:
  - tenancy scoping (cannot access other company)
  - approval workflow transitions
  - asset assignment/return flow

---

## 12) “Codex Instructions” (Paste into Codex)
When using Codex, always begin with:

"Read FLOWDESK_ARCHITECTURE.md, FLOWDESK_DATABASE.md, and FLOWDESK_CODING_RULES.md first.
Follow them strictly.
Do not invent new folder structures.
Every query must be scoped by company_id.
Departments are mandatory for users and requests.
Use Actions for business logic and ActivityLogger for logs."

End of document.

---

## Addendum: Livewire Purity and Temporary Fallbacks
- Default rule: module CRUD actions should be Livewire-driven once stabilized.
- Do not keep temporary HTTP fallback routes/controllers for Livewire CRUD unless explicitly approved.
- If a fallback is added for emergency debugging, remove it before module lock.
- Livewire forms should use semantic submit handling (`wire:submit.prevent` + submit button).
- Any non-standard event workaround must be documented and removed in cleanup.

## Addendum: Livewire Stability Guardrails
- Do not create component methods using Livewire lifecycle hook prefixes unless they are real hooks:
  - avoid names like `hydrate*`, `dehydrate*`, `updating*`, `updated*`, `boot*`, `mount*`.
- For private helper methods, use neutral prefixes such as `build*`, `fill*`, `map*`, `refresh*`.
- For file downloads from Laravel storage, use the correct return type:
  - `Symfony\\Component\\HttpFoundation\\StreamedResponse`.
- Keep destructive business actions (void/delete) in dedicated confirm flows, not mixed into read-only detail views.
- Before module lock, run target feature tests and clear framework caches.
