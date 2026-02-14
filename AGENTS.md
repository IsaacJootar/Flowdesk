# AGENTS.md (Flowdesk Codex Instructions)

You are implementing a Laravel 11 + Livewire 3 SaaS called Flowdesk.

## Read these first (mandatory)
1) FLOWDESK_ARCHITECTURE.md
2) FLOWDESK_DATABASE.md
3) FLOWDESK_CODING_RULES.md

Do not invent a different structure.

---

## Global requirements (non-negotiable)

### Tenancy / company scoping
- Flowdesk is multi-company SaaS.
- All company-owned tables include `company_id`.
- Every query must be scoped by the authenticated user’s `company_id`.
- Never accept `company_id` from request input.

### Departments are mandatory
- Every user must belong to a department.
- Every request must belong to a department.
- Auto-create a default department named **General** for every company.
- Ensure app logic enforces department assignment during setup.

### Roles (simple for v1)
owner, finance, manager, staff, auditor

### Audit logs are mandatory
- Implement activity_logs table and ActivityLogger service.
- Log key actions across requests, approvals, vendors, assets, expenses.

### UI requirements
- Stripe/Notion/Brex-style: clean, card-driven, modern.
- Use Livewire SPA-like updates where it helps (tables, filters, modals, slide-overs).
- Implement loading states (wire:loading) and prevent double submits.
- Use lazy-loading where needed (heavy tables/charts/panels load on demand).

---

## Implementation scope (Skeleton only)
Create the project skeleton and foundations only:
1) Folder structure from FLOWDESK_CODING_RULES.md
2) Core migrations + models for:
   - companies, departments, users additions
   - activity_logs
3) Base tenancy helpers:
   - CompanyScoped trait
   - EnsureCompanyContext middleware
4) Base layout (sidebar + topbar) and route scaffolding
5) Livewire: Dashboard shell page with placeholder metric cards and lazy-loading pattern
6) Settings: Company setup page (create company + General dept + set user role=owner)
7) Policies scaffolding for core models (empty rules acceptable for now)

Do NOT build full modules (Requests, Assets CRUD) yet in this skeleton step.

---

## Output rules
- Prefer clear, minimal code.
- Keep Livewire components thin; heavy logic goes into Actions/Services.
- Ensure everything compiles and migrations run without errors.
- Provide a final checklist of what was created/modified.
