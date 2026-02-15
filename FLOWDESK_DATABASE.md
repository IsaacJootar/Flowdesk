# FLOWDESK_DATABASE.md
Stage 1 database design for Flowdesk (Multi-Company SaaS).
Tenancy model: single database, shared tables, **company_id** on all company-owned records.

---

## 0) Conventions
### Primary keys
- Use `id` as BIGINT/unsigned.

### Tenancy scoping
- Any company-owned table MUST include:
  - `company_id` (FK to `companies.id`, indexed)

### Audit fields
- Prefer standard:
  - `created_by` (FK to users.id, nullable)
  - `updated_by` (FK to users.id, nullable)
- Use `timestamps()` everywhere.
- Use `softDeletes()` on critical business tables.

### Status fields
- Use `status` as string (enum-like values in code).

### Money fields
- Use `amount` as BIGINT in **kobo** (or cents) to avoid float issues.
  - Example: ₦12,500.00 → 1250000

---

## 1) Tenancy / Identity

### 1.1 companies
Stores each organization using Flowdesk.

**Columns**
- id
- name
- slug (unique)
- email (nullable)
- phone (nullable)
- industry (nullable)
- currency_code (default: NGN)
- timezone (default: Africa/Lagos)
- address (nullable)
- is_active (bool default true)
- created_by (nullable)
- updated_by (nullable)
- timestamps
- softDeletes

**Indexes**
- unique(slug)
- index(is_active)

---

### 1.2 users
If a user belongs to only one company in v1, keep `company_id` directly on users.

**Columns**
- id
- company_id (FK)
- name
- email (unique within system OR unique globally; choose globally for simplicity)
- phone (nullable)
- password
- role (string: owner|finance|manager|staff|auditor)
- department_id (nullable FK)
- is_active (bool default true)
- last_login_at (nullable)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(role)
- index(department_id)
- unique(email)

---

### 1.3 departments
Optional but recommended for budgets and manager scope.

**Columns**
- id
- company_id (FK)
- name
- code (nullable)
- manager_user_id (nullable FK users.id)
- is_active (bool default true)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(manager_user_id)

---

## 2) Spending & Approvals

### 2.1 requests
Main request record (purchase/payment/expense/travel-cash request).
This is the heart of Stage 1.

**Columns**
- id
- company_id (FK)
- request_code (unique per company, e.g. FD-REQ-000012)
- type (string: purchase|payment|expense|travel)
- title (string)
- description (text nullable)
- department_id (nullable FK)
- requested_by (FK users.id)
- total_amount (bigint kobo, computed from items or manual)
- currency_code (default company currency)
- needed_by (date nullable)
- status (draft|pending_manager|pending_finance|approved|rejected|cancelled)
- current_step (string nullable: manager|finance)
- manager_approved_at (nullable datetime)
- finance_approved_at (nullable datetime)
- rejected_at (nullable datetime)
- rejected_by (nullable FK users.id)
- rejection_reason (text nullable)
- created_by (nullable; set = requested_by)
- updated_by (nullable)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(type)
- index(status)
- index(department_id)
- index(requested_by)
- unique(company_id, request_code)

---

### 2.2 request_items
For requests containing multiple line items (recommended).

**Columns**
- id
- company_id (FK)
- request_id (FK)
- name (string)
- description (text nullable)
- quantity (int default 1)
- unit_cost (bigint kobo)
- line_total (bigint kobo)
- vendor_id (nullable FK vendors.id)
- category (nullable string)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(request_id)
- index(vendor_id)

---

### 2.3 request_approvals
Approval trail for auditable workflow.

**Columns**
- id
- company_id (FK)
- request_id (FK)
- step (string: manager|finance)
- action (string: approved|rejected)
- acted_by (FK users.id)
- acted_at (datetime)
- comment (text nullable)
- from_status (string)
- to_status (string)
- timestamps

**Indexes**
- index(company_id)
- index(request_id)
- index(step)
- index(acted_by)

---

### 2.4 expenses
Records actual spending.
Optionally linked to an approved request.

**Columns**
- id
- company_id (FK)
- expense_code (unique per company, e.g. FD-EXP-000021)
- request_id (nullable FK requests.id)
- department_id (nullable FK)
- vendor_id (nullable FK)
- paid_by_user_id (nullable FK users.id)  (who executed payment)
- title (string)
- description (text nullable)
- amount (bigint kobo)
- currency_code (default company currency)
- expense_date (date)
- payment_method (nullable string: cash|transfer|pos|online|cheque)
- status (draft|submitted|approved|rejected|reconciled)  (keep simple v1)
- created_by (FK users.id)
- approved_by (nullable FK users.id)
- approved_at (nullable datetime)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(request_id)
- index(department_id)
- index(vendor_id)
- index(status)
- unique(company_id, expense_code)

---

### 2.5 expense_receipts
Stores uploaded proof/receipts.

**Columns**
- id
- company_id (FK)
- expense_id (FK)
- file_path (string)
- file_name (string nullable)
- file_size (bigint nullable)
- mime_type (string nullable)
- uploaded_by (FK users.id)
- uploaded_at (datetime)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(expense_id)
- index(uploaded_by)

---

## 3) Vendors & Budgets

### 3.1 vendors
Company vendor directory.

**Columns**
- id
- company_id (FK)
- name (string)
- vendor_type (nullable string: supplier|contractor|service|other)
- contact_name (nullable string)
- contact_email (nullable string)
- contact_phone (nullable string)
- address (nullable text)
- bank_name (nullable string)
- bank_account_name (nullable string)
- bank_account_number (nullable string)
- notes (nullable text)
- is_active (bool default true)
- created_by (nullable FK users.id)
- updated_by (nullable FK users.id)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(is_active)
- index(vendor_type)

---

### 3.2 department_budgets
Budgets per department (monthly or yearly).

**Columns**
- id
- company_id (FK)
- department_id (FK)
- period_type (string: monthly|quarterly|yearly)
- period_start (date)
- period_end (date)
- allocated_amount (bigint kobo)
- used_amount (bigint kobo default 0)  (can be computed later)
- remaining_amount (bigint kobo default 0) (can be computed)
- status (active|closed)
- created_by (FK users.id)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(department_id)
- index(period_type)
- index(status)

---

## 4) Assets (Stage 1)

### 4.1 assets
Company asset register.

**Columns**
- id
- company_id (FK)
- asset_code (unique per company, e.g. FD-AST-000120)
- name (string)                 (e.g. HP Elitebook 840)
- asset_type (string)           (laptop|vehicle|equipment|tool|other)
- brand (nullable string)
- model (nullable string)
- serial_number (nullable string)
- tag_number (nullable string)  (internal tag/QR label)
- purchase_date (nullable date)
- purchase_cost (nullable bigint kobo)
- vendor_id (nullable FK vendors.id)
- condition (nullable string: new|good|fair|poor)
- status (available|assigned|under_repair|retired|lost)
- location (nullable string)    (branch/office)
- notes (nullable text)
- created_by (FK users.id)
- updated_by (nullable FK users.id)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(asset_type)
- index(status)
- index(vendor_id)
- unique(company_id, asset_code)
- index(serial_number)

---

### 4.2 asset_assignments
Tracks assignment history to staff.

**Columns**
- id
- company_id (FK)
- asset_id (FK assets.id)
- assigned_to_user_id (FK users.id)
- assigned_by_user_id (FK users.id)
- assigned_at (datetime)
- expected_return_at (nullable datetime)
- returned_at (nullable datetime)
- return_condition (nullable string)
- status (active|returned|transferred)
- notes (nullable text)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(asset_id)
- index(assigned_to_user_id)
- index(status)

---

### 4.3 asset_maintenance_logs
Repairs and maintenance.

**Columns**
- id
- company_id (FK)
- asset_id (FK)
- logged_by (FK users.id)
- maintenance_type (nullable string: repair|service|inspection|other)
- description (text)
- cost (nullable bigint kobo)
- vendor_id (nullable FK vendors.id)
- started_at (nullable datetime)
- completed_at (nullable datetime)
- status (open|in_progress|done)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(asset_id)
- index(status)
- index(vendor_id)

---

### 4.4 asset_returns
Return/offboarding checklist record (optional but recommended).

**Columns**
- id
- company_id (FK)
- asset_assignment_id (FK asset_assignments.id)
- checked_by (FK users.id)
- checklist_json (json)  (e.g. { "laptop": true, "charger": true, "bag": false })
- notes (nullable text)
- returned_at (datetime)
- timestamps
- softDeletes

**Indexes**
- index(company_id)
- index(asset_assignment_id)
- index(checked_by)

---

## 5) Audit / Activity Logs (Mandatory)

### 5.1 activity_logs
Single table for all major events.

**Columns**
- id
- company_id (FK)
- user_id (nullable FK)
- action (string)               (e.g. request.created, request.approved, asset.assigned)
- entity_type (string)          (e.g. Request, Asset, Vendor)
- entity_id (bigint)
- metadata (json nullable)      (store old/new values, comments, ip, user agent)
- created_at (datetime)

**Indexes**
- index(company_id)
- index(user_id)
- index(action)
- index(entity_type, entity_id)

---

## 6) Recommended Foreign Key Behavior
- Use `restrictOnDelete()` for most relationships to preserve audit history.
- Use `nullOnDelete()` where appropriate (e.g., vendor linked to old expense).
- Never cascade delete core financial history.

---

## 7) Notes for Stage 2+
Future tables (not in stage 1):
- travel_trips
- wallets
- virtual_cards
- card_transactions
- policies (spend policies)
- exports / integrations

---

## 8) Minimum Seed Data
- Create company
- Create departments
- Create owner user
- Create default budgets (optional)
- Create sample statuses (enums in code)

End of document.

---

## Addendum: Field Requirement Matrix (Stage 1)
Before implementation, each module must define required vs optional fields in writing.

Vendor module baseline:
- Required: name, vendor_type, contact_person, phone, email, address, bank_name, account_name, account_number, notes, is_active.
- Optional: none (except system fields like timestamps/soft deletes).
