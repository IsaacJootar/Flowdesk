# Flowdesk Accounting Export and Sync Phase

This phase connects Flowdesk's completed financial activity to accounting systems such as CSV imports, QuickBooks Online, Sage Business Cloud, and Xero.

The goal is simple: finance should record or approve work once in Flowdesk, then send the final accounting-ready record to their books without retyping it.

---

## Core Rule

Flowdesk must not sync accounting records from approval alone.

Approval means: this spend is allowed.

Accounting export/sync means: a real financial event is ready for the books.

Use these source events:

| Flowdesk event | Meaning | Accounting action |
|---|---|---|
| Payout completed | Money has left or is confirmed for the vendor/staff | Queue accounting event |
| Expense posted | A direct or request-linked expense has been recorded | Queue accounting event |
| Vendor invoice matched and paid | Invoice payment is complete | Queue accounting event |
| Purchase order approved | A purchase commitment exists, but money may not have moved | Queue purchase order event only |

Do not push draft requests, pending approvals, failed payouts, cancelled requests, or voided expenses as normal accounting entries.

If an already exported/synced expense is voided later, create a reversal/correction event instead of editing history silently.

---

## Tenancy And Access Rules

Every integration table is company-owned and must include `company_id`.

All queries must be scoped by the authenticated user's `company_id`, unless the route is an explicitly platform-only tenant management route.

Never accept `company_id` from request input.

Access rules:

| Role | Access |
|---|---|
| owner | Manage mappings, integrations, exports, retries |
| finance | Manage mappings, exports, retries |
| auditor | View export/sync history only |
| manager | No accounting integration settings |
| staff | No accounting integration settings |
| platform user | Manage tenant-level access only through platform routes |

Platform users can manage organizations, but company data must not leak across tenants.

---

## What We Are Building

Build in this order:

| # | Module | What it does | Why it comes here |
|---|---|---|---|
| 1 | Accounting foundation | Shared categories, mappings, event queue, export history | Required by every provider |
| 2 | CSV export | Download accounting-ready transactions | Immediate value, lowest risk |
| 3 | Provider-ready integration shell | Secure connection records, status UI, adapter contract | Prevents three different implementations |
| 4 | QuickBooks Online | Push completed events to QuickBooks | First live provider |
| 5 | Sage Business Cloud | Push completed events to Sage | Reuses same engine |
| 6 | Xero | Push completed events to Xero | Reuses same engine |

---

## Accounting Categories

These are the Flowdesk categories that move money or create accounting commitments.

Each company maps these categories to its own chart of accounts before export or sync is activated.

| # | Flowdesk category | Category key | Typical account |
|---|---|---|---|
| 1 | Spend - Operations | `spend_operations` | Operating Expenses |
| 2 | Spend - Travel | `spend_travel` | Travel & Transport |
| 3 | Spend - Utilities | `spend_utilities` | Utilities |
| 4 | Spend - Software | `spend_software` | Software & Subscriptions |
| 5 | Spend - Procurement | `spend_procurement` | Procurement / Purchases |
| 6 | Spend - Maintenance | `spend_maintenance` | Maintenance & Repairs |
| 7 | Spend - Training | `spend_training` | Training & Development |
| 8 | Vendor Payment | `vendor_payment` | Accounts Payable |
| 9 | Staff Reimbursement | `staff_reimbursement` | Staff Expenses |
| 10 | Purchase Order | `purchase_order` | Purchases / Stock |
| 11 | Vendor Invoice | `vendor_invoice` | Accounts Payable |
| 12 | Petty Cash | `petty_cash` | Petty Cash / Cash at Hand |

---

## Dedicated Accounting Category Key

Flowdesk needs a dedicated `accounting_category_key` before accounting export or provider sync is built.

This field answers one question:

"When this record goes to accounting, which account mapping should it use?"

It is not the approval status.

It is not the payment method.

It is not the department budget.

It is the accounting meaning of the spend.

Examples:

| Record | Payment method | Department | Accounting category key |
|---|---|---|---|
| Office internet bill | Bank transfer | Operations | `spend_utilities` |
| Staff flight reimbursement | Wallet payout | HR | `spend_travel` |
| Laptop purchase | Bank transfer | IT | `spend_procurement` |
| Software subscription | Card | Engineering | `spend_software` |
| Generator repair | Cash | Operations | `spend_maintenance` |

Why this matters:

The payment method only explains how money moved. It does not explain what the money was for.

A bank transfer can pay for travel, utilities, software, procurement, maintenance, or training. Accounting needs the spend meaning, not just the payment rail.

### Where The Field Should Live

Add `accounting_category_key` to the records that carry financial intent or final financial posting.

| Table | Required timing | Why |
|---|---|---|
| `requests` | Required before submit | Simple one-purpose requests need a category |
| `request_items` | Required before submit when line items exist | Multi-line requests may have different categories per item |
| `expenses` | Required before posting | Expenses are the accounting-ready spending record |
| `vendor_invoices` | Required before invoice can sync, when module exists | Invoice payments need account mapping |
| `purchase_orders` | Required before PO can sync, when module exists | Purchase commitments need account mapping |

For the current Flowdesk build, the practical first implementation should cover:

- `requests.accounting_category_key`
- `request_items.accounting_category_key`
- `expenses.accounting_category_key`

Vendor invoice and purchase order fields should be added when those modules become full records.

### Workflow Rule

For request-linked spending:

1. Staff creates a request.
2. Staff or finance chooses a simple "Spend Type" in the UI.
3. Flowdesk stores the internal `accounting_category_key`.
4. Manager/finance approves the request.
5. Payout is completed.
6. Expense is created from the approved request.
7. The expense inherits the request or item category key.
8. Accounting export/sync reads the expense category key and maps it to the company's account code.

For direct expenses:

1. Finance records the expense.
2. Finance chooses the "Spend Type".
3. Flowdesk stores `expenses.accounting_category_key`.
4. The expense cannot be posted as accounting-ready unless the key is present.
5. Export/sync uses that key.

For multi-line requests:

1. Each request item can carry its own `accounting_category_key`.
2. If all items share one category, the request-level key can be used as the default.
3. Accounting event creation should preserve line-level categories where the export/provider supports line items.
4. If the target export/provider only supports one category for a record, Flowdesk should either split the accounting event by category or block with a clear message.

### UI Language

Do not show users the technical field name `accounting_category_key`.

Use this label:

```text
Spend Type
```

Use simple options:

- Operations
- Travel
- Utilities
- Software
- Procurement
- Maintenance
- Training
- Vendor payment
- Staff reimbursement
- Purchase order
- Vendor invoice
- Petty cash

Internally, Flowdesk stores keys such as:

- `spend_operations`
- `spend_travel`
- `spend_software`
- `vendor_payment`
- `staff_reimbursement`

### When It Is Required

| Workflow stage | Rule |
|---|---|
| Draft request | Optional |
| Submitted request | Required |
| Request approval | Must already be present |
| Direct expense draft | Optional |
| Direct expense posting | Required |
| Payout-created expense | Inherited from approved request/item and required before accounting event |
| CSV export | Required |
| QuickBooks/Sage/Xero sync | Required |

Recommended validation:

- Block request submission if no spend type is selected.
- Block direct expense posting if no spend type is selected.
- Mark accounting events as `needs_mapping` if spend type exists but chart-of-account mapping is missing.
- Block export/sync if spend type is missing, because the record is not accounting-ready.

### Database Notes

Add the field as nullable first for backward compatibility with existing records:

```text
accounting_category_key nullable string indexed
```

Then backfill existing records where the category is obvious.

Examples:

- existing travel requests -> `spend_travel`
- petty cash/cash payment expenses -> `petty_cash`
- staff reimbursement requests -> `staff_reimbursement`
- vendor payout records -> `vendor_payment`

Where the category cannot be known safely, leave it blank and show it in a cleanup queue for finance.

Do not guess silently.

---

## Shared Database Design

### `chart_of_account_mappings`

Stores each company's mapping from Flowdesk category to accounting account.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `company_id` | Required, indexed |
| `category_key` | Required, one of the locked Flowdesk category keys |
| `account_code` | Required for activation/export |
| `account_name` | Optional display label |
| `provider` | Required; use `csv` for the generic CSV mapping, otherwise `quickbooks`, `sage`, or `xero` |
| `provider_account_id` | Nullable external account ID |
| `created_by` | User who created it |
| `updated_by` | User who last changed it |
| timestamps | Required |

Indexes:

- unique `company_id`, `provider`, `category_key`
- index `company_id`

### `accounting_integrations`

Stores one provider connection per company.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `company_id` | Required, indexed |
| `provider` | `quickbooks`, `sage`, or `xero` |
| `status` | `disconnected`, `connected`, `expired`, `disabled` |
| `external_tenant_id` | Realm/company/tenant ID from provider |
| `access_token` | Encrypted |
| `refresh_token` | Encrypted |
| `token_expires_at` | Nullable datetime |
| `last_synced_at` | Nullable datetime |
| `metadata` | JSON for provider-specific settings |
| `created_by` | User who connected it |
| `updated_by` | User who last changed it |
| timestamps | Required |

Indexes:

- unique `company_id`, `provider`
- index `company_id`, `status`

### `accounting_sync_events`

This is the integration outbox. It is the source of truth for what should be exported or synced.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `company_id` | Required, indexed |
| `source_type` | Example: `expense`, `payout`, `vendor_invoice`, `purchase_order` |
| `source_id` | ID of the source record |
| `event_type` | Example: `expense_posted`, `payout_completed`, `purchase_order_approved`, `expense_voided` |
| `category_key` | Required Flowdesk accounting category |
| `amount` | Major currency units, matching current Flowdesk money rule |
| `currency_code` | Example: `NGN` |
| `event_date` | Date used for accounting |
| `description` | Human-readable accounting memo |
| `debit_account_code` | Nullable until mapped |
| `credit_account_code` | Nullable until mapped |
| `status` | `pending`, `needs_mapping`, `exported`, `syncing`, `synced`, `failed`, `skipped` |
| `attempt_count` | Retry count |
| `next_attempt_at` | Nullable datetime |
| `last_error` | Nullable text |
| `provider` | Nullable; set for provider sync |
| `provider_record_id` | Nullable external ID after success |
| `export_batch_id` | Nullable link to CSV batch |
| `metadata` | JSON snapshot of source details |
| timestamps | Required |

Indexes:

- index `company_id`, `status`
- index `company_id`, `event_date`
- index `company_id`, `source_type`, `source_id`
- unique `company_id`, `source_type`, `source_id`, `event_type`, `provider`

### `accounting_export_batches`

Stores CSV export runs.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `company_id` | Required, indexed |
| `from_date` | Export start date |
| `to_date` | Export end date |
| `status` | `completed`, `completed_with_warnings`, `failed` |
| `row_count` | Number of exported rows |
| `warning_count` | Number of missing mapping or skipped rows |
| `file_path` | Stored CSV path |
| `created_by` | User who downloaded/exported |
| `metadata` | JSON summary |
| timestamps | Required |

### `accounting_provider_accounts`

Stores accounts pulled from QuickBooks/Sage/Xero after connection.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `company_id` | Required, indexed |
| `provider` | `quickbooks`, `sage`, or `xero` |
| `provider_account_id` | External account ID |
| `account_code` | External account code |
| `account_name` | External account name |
| `account_type` | External account type |
| `is_active` | Boolean |
| `metadata` | JSON raw details |
| timestamps | Required |

Indexes:

- unique `company_id`, `provider`, `provider_account_id`
- index `company_id`, `provider`, `is_active`

---

## Shared Services And Actions

Keep Livewire components thin. Put business rules in Actions/Services.

Recommended files:

| File | Responsibility |
|---|---|
| `app/Enums/AccountingProvider.php` | Provider names |
| `app/Enums/AccountingSyncStatus.php` | Sync/export statuses |
| `app/Enums/AccountingCategory.php` | Locked Flowdesk category keys |
| `app/Actions/Accounting/SaveChartOfAccountMapping.php` | Validate and save mappings |
| `app/Actions/Accounting/CreateAccountingSyncEvent.php` | Create idempotent outbox event |
| `app/Actions/Accounting/ExportAccountingCsv.php` | Build CSV batch |
| `app/Actions/Accounting/RetryAccountingSyncEvent.php` | Retry one failed event |
| `app/Services/Accounting/AccountingEventBuilder.php` | Convert Flowdesk source records into accounting events |
| `app/Services/Accounting/AccountMappingService.php` | Resolve debit/credit accounts |
| `app/Services/Accounting/AccountingExportCsvWriter.php` | Create CSV rows and warning rows |
| `app/Services/Accounting/Providers/AccountingProviderClient.php` | Interface for provider clients |
| `app/Services/Accounting/Providers/QuickBooksClient.php` | QuickBooks API adapter |
| `app/Services/Accounting/Providers/SageClient.php` | Sage API adapter |
| `app/Services/Accounting/Providers/XeroClient.php` | Xero API adapter |

Every Action must:

- scope by company
- authorize by role/policy
- validate mapping completeness
- write activity logs for important changes
- avoid blocking payout/request/expense state transitions if an external provider is down

---

## Event Flow

### Payout completed

1. Payout becomes completed.
2. Flowdesk creates or updates the matching expense if required by current workflow.
3. `CreateAccountingSyncEvent` creates an outbox event.
4. If no mapping exists, event status becomes `needs_mapping`.
5. If mapping exists, event status becomes `pending`.
6. CSV export can include it.
7. Active provider sync can pick it up through a queued job.

### Expense posted

1. Expense is posted.
2. Flowdesk checks whether it is direct or request-linked.
3. Event builder assigns category, source type, amount, currency, department, vendor, and description.
4. Event is created idempotently.

### Expense voided

1. Expense status becomes void.
2. If it was already exported/synced, create reversal event.
3. Do not delete the original export/sync event.

### Purchase order approved

1. Purchase order is approved.
2. Create a purchase order accounting event.
3. Do not treat this as a paid expense unless payment is completed later.

---

## Module 1 - Accounting Foundation

Build this before CSV or provider API work.

### UI

Where:

- Settings -> Chart of Accounts
- Route: `/settings/chart-of-accounts`

The page should show:

- category name
- simple description
- account code input
- account name input
- mapping status
- last updated by/at

Simple language:

- "Connect each Flowdesk spend type to the account in your books."
- "Missing account codes will stop export and sync for those records."

### Rules

- Owner and finance can edit.
- Auditor can view only.
- Manager and staff cannot access.
- All 12 categories must be mapped before activating provider sync.
- CSV export may run with warnings or be blocked depending on final product choice. Recommended v1: block normal export when any included event is missing a mapping, and show the exact missing category.

---

## Module 2 - CSV Export

Where:

- Reports -> Budget to Payment Trace, or Reports -> Accounting Export
- Recommended route: `/reports/accounting-export`

What it does:

1. Admin selects date range.
2. Flowdesk pulls `accounting_sync_events` for that company and date range.
3. Flowdesk resolves mappings.
4. Flowdesk creates a CSV export batch.
5. User downloads the file.
6. Export history shows recent files and warnings.

CSV columns:

```text
Date,Reference,Source Type,Description,Debit Account,Credit Account,Amount,Currency,Department,Vendor,Flowdesk Trace ID
```

Missing mapping behavior:

- If any event has no account mapping, show a clear message:
  "3 records need account mapping before export."
- List missing categories and affected records.
- Do not create a misleading CSV with incomplete accounting rows.

---

## Module 3 - Provider-Ready Integration Shell

Build the shared integration structure before QuickBooks.

Where:

- Settings -> Integrations
- Route: `/settings/integrations`

Each provider page should show:

- connection status
- connect/re-authorize/disconnect controls
- mapped account count
- last sync time
- failed sync count
- retry actions
- recent sync events

Security:

- OAuth tokens encrypted at rest.
- Only owner/finance can connect or disconnect.
- Auditor can view history only.
- Disconnect stops future syncs but does not delete past records.

Queued jobs:

- Provider sync jobs must run in the background.
- Provider failure must not break a payout, expense, or approval flow.
- Retry schedule: 1 minute, 5 minutes, 15 minutes.
- After 3 failed attempts, mark `failed` and show to finance.

---

## Module 4 - QuickBooks Online

Where:

- Settings -> Integrations -> QuickBooks
- Route: `/settings/integrations/quickbooks`

OAuth storage:

- `external_tenant_id` stores QuickBooks realm ID.
- `access_token` encrypted.
- `refresh_token` encrypted.
- `token_expires_at` stored.

Provider account sync:

- Pull live QuickBooks chart of accounts.
- Store accounts in `accounting_provider_accounts`.
- Mapping dropdown uses real QuickBooks accounts.

What gets created:

| Flowdesk event | QuickBooks record |
|---|---|
| Payout completed for vendor invoice | Bill + Bill Payment |
| Expense posted | Expense or Bill, depending on payment/vendor context |
| Purchase order approved | Purchase Order |
| Expense voided after sync | Reversal/correction entry, implementation to confirm |

Do not create a QuickBooks payment from a request approval alone.

---

## Module 5 - Sage Business Cloud

Where:

- Settings -> Integrations -> Sage
- Route: `/settings/integrations/sage`

Provider account sync:

- Pull Sage ledger accounts.
- Store accounts in `accounting_provider_accounts`.
- Mapping dropdown uses real Sage ledger accounts.

What gets created:

| Flowdesk event | Sage record |
|---|---|
| Payout completed for vendor invoice | Purchase Invoice + Supplier Payment |
| Expense posted | Payment/expense record, depending on Sage API fit |
| Purchase order approved | Purchase Order |
| Expense voided after sync | Reversal/correction entry, implementation to confirm |

Use the same outbox, retry, mapping, and dashboard pattern as QuickBooks.

---

## Module 6 - Xero

Where:

- Settings -> Integrations -> Xero
- Route: `/settings/integrations/xero`

OAuth storage:

- `external_tenant_id` stores Xero tenant ID.
- `access_token` encrypted.
- `refresh_token` encrypted.
- `token_expires_at` stored.

Provider account sync:

- Pull Xero Accounts.
- Store accounts in `accounting_provider_accounts`.
- Mapping dropdown uses real Xero accounts.

What gets created:

| Flowdesk event | Xero record |
|---|---|
| Payout completed for vendor invoice | Bill (`ACCPAY`) + Payment |
| Expense posted | Spend Money or Bill, depending on vendor/payment context |
| Purchase order approved | Purchase Order |
| Expense voided after sync | Credit note/reversal, implementation to confirm |

Xero access tokens expire quickly. Refresh automatically in the background.

---

## What Good Looks Like

A finance admin should be able to:

1. Open Settings -> Chart of Accounts.
2. Map Flowdesk spend categories to their own accounting accounts.
3. Export completed spending to CSV without missing account codes.
4. Later connect QuickBooks, Sage, or Xero.
5. Pull real accounts from the provider.
6. Map categories to real provider accounts.
7. Turn on sync.
8. See every completed payout or posted expense move through: pending -> synced, or failed with a clear reason.
9. Retry failed records without touching the original request, payout, or expense history.

---

## Implementation Checklist

### Phase 1A - Spend Type Foundation

- [x] Add locked accounting category enum for the 12 Spend Type options.
- [x] Add nullable `accounting_category_key` fields to `requests`, `request_items`, and `expenses`.
- [x] Store Spend Type on request drafts and direct expenses.
- [x] Require Spend Type before request submission.
- [x] Require Spend Type before direct expense posting.
- [x] Let request-linked expenses inherit the request Spend Type when there is one clear category.
- [x] Show simple UI labels: "Spend Type" instead of `accounting_category_key`.
- [x] Show Spend Type in request details, line items, expense list, and expense details.

### Phase 1 - Accounting Foundation

- [x] Create accounting enums.
- [x] Create `chart_of_account_mappings` migration and model.
- [ ] Create migrations and models for integrations, provider accounts, sync events, and export batches.
- [x] Add access checks for Chart of Accounts.
- [x] Add ActivityLogger entries for mapping changes.
- [ ] Add ActivityLogger entries for integration status changes.
- [x] Build Settings -> Chart of Accounts.
- [x] Confirm category source fields exist in request/expense flows.
- [ ] Confirm payout completion flow can pass Spend Type into accounting events.

Completed in this slice:

- Settings -> Chart of Accounts is available at `/settings/chart-of-accounts`.
- Sidebar shows "Accounting Setup" for owner, finance, and auditor when Expenses is enabled.
- Owner and finance can map Spend Types to account code/account name.
- Auditor can view mappings without editing.
- Manager and staff cannot access the page.
- Each save writes an audit log entry.
- Mapping rows are scoped to the authenticated user's company only.

### Phase 2 - Event Outbox

- [ ] Add `CreateAccountingSyncEvent`.
- [ ] Add `AccountingEventBuilder`.
- [ ] Create events from completed payout.
- [ ] Create events from posted expense.
- [ ] Create reversal events from voided synced/exported expense.
- [ ] Add idempotency checks.

### Phase 3 - CSV Export

- [ ] Build export page.
- [ ] Add date range filters.
- [ ] Validate mappings before export.
- [ ] Create CSV file.
- [ ] Store export batch.
- [ ] Show recent export history.

### Phase 4 - Integration Shell

- [ ] Create Settings -> Integrations page.
- [ ] Add provider connection status cards.
- [ ] Add provider account list refresh action.
- [ ] Add failed sync event list.
- [ ] Add retry one/retry all actions.

### Phase 5 - QuickBooks

- [ ] Add OAuth connect/callback.
- [ ] Store encrypted tokens.
- [ ] Pull chart of accounts.
- [ ] Add QuickBooks provider client.
- [ ] Add queued sync job.
- [ ] Add retry handling.

### Phase 6 - Sage

- [ ] Add OAuth connect/callback.
- [ ] Store encrypted tokens.
- [ ] Pull ledger accounts.
- [ ] Add Sage provider client.
- [ ] Add queued sync job.
- [ ] Add retry handling.

### Phase 7 - Xero

- [ ] Add OAuth connect/callback.
- [ ] Store encrypted tokens.
- [ ] Pull accounts.
- [ ] Add Xero provider client.
- [ ] Add queued sync job.
- [ ] Add token refresh handling.

---

## Notes Before Building

The current Flowdesk money rule says the app uses major currency units internally. Do not silently multiply or divide by 100 in the accounting layer.

At every external provider boundary, document what the provider expects.

Provider APIs may differ on whether an item should become a bill, expense, purchase order, payment, credit note, or journal entry. Keep those decisions inside provider adapters, not scattered through Livewire pages.

Do not let a provider API error block the user from completing a valid Flowdesk workflow. Flowdesk should record the failure, show it to finance, and allow retry.
