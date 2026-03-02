# FLOWDESK_NEXT_MODULES_BLUEPRINT.md

Last updated: 2026-03-02
Owner: Product + Engineering

## 1) Purpose
This document defines the next two modules that will make Flowdesk a complete operations and financial control platform without breaking the current foundation.

New modules:
1. Commitments and Procurement Control (PO and 3-way match)
2. Treasury and Bank Reconciliation

These modules will integrate with what Flowdesk already has: Requests, Approvals, Budgets, Vendors, Expenses, Execution, Audit Logs, Reports, and Platform Operations.

## 2) Why These Two Modules
Flowdesk already controls approvals and execution operations well. The remaining gaps are:
1. Pre-payment procurement control (formal PO discipline)
2. Post-payment cash truth (bank-level reconciliation)

Adding these closes the full loop:
Request -> Approval -> Budget Control -> Procurement/Expense -> Payment Execution -> Bank Settlement Proof.

## 3) Current Foundation We Will Reuse
We will reuse existing components instead of rebuilding:
1. `SpendRequest` approval workflow
2. `Expense` lifecycle and controls
3. `Vendor` records and vendor invoice/payment timeline
4. `DepartmentBudget` controls and reporting
5. Execution engine attempts (`billing`, `payout`, `webhook`)
6. Tenant and platform audit logging (`tenant_audit_events`)
7. Reports center and role/entitlement framework

## 4) Target Operating Model (Two Spend Lanes)
Flowdesk will support two budget-governed spend lanes in parallel.

### Lane A: Procurement Lane (PO-based)
Used for planned or policy-required purchases.
1. Approved spend request -> Convert to PO
2. PO issuance to vendor
3. Goods or service receipt
4. Vendor invoice upload
5. 3-way match (PO vs Receipt vs Invoice)
6. If matched, allow payment execution
7. If mismatch, move to exceptions queue

### Lane B: Direct Expense Lane (Non-PO)
Used for operational spend recording where purchase already happened and proof is uploaded.
1. Expense is submitted with evidence (receipt/invoice/proof) via current expense flow.
2. Approver validates policy, then expense is posted.
3. Budget is consumed directly as Spent (no PO commitment stage).
4. Expense does not enter payout execution by default because it is post-spend recording.
5. If marked reimbursable, a reimbursement payout is created and then enters execution.
6. Treasury reconciles either:
   - reimbursement payout vs bank line, or
   - direct expense evidence vs bank/card line (if company-funded transaction exists).

## 5) Budget Model Across Both Lanes
All spend still references budgets, with different impact timing.

Budget fields to standardize:
1. Allocated
2. Committed
3. Spent
4. Available = Allocated - Committed - Spent

Behavior:
1. Request stage: budget availability check (optional soft reserve)
2. PO lane: PO approval increases Committed
3. PO lane: matched invoice/payment moves Committed -> Spent
4. Direct expense lane: approved or posted expense goes directly to Spent

Result:
1. Procurement spend is controlled before payment
2. Daily operations remain flexible for small non-PO spend

## 6) Module A: Commitments and Procurement Control

### 6.1 Objective
Add formal procurement governance with PO lifecycle and 3-way matching, while keeping existing request and vendor flows intact.

### 6.2 Core Features
1. Convert approved request to PO draft
2. PO lifecycle: draft, issued, part_received, received, invoiced, closed, canceled
3. Goods/service receipt tracking
4. Invoice to PO linkage
5. 3-way match engine with tolerance rules
6. Procurement exception queue
7. Budget commitment postings
8. Complete audit trail for issue, match, override, and close

### 6.3 Proposed Data Objects (Additive)
1. `purchase_orders`
2. `purchase_order_items`
3. `goods_receipts`
4. `goods_receipt_items`
5. `procurement_commitments`
6. `invoice_match_results`
7. `invoice_match_exceptions`

All objects are additive and linked by foreign keys to existing entities (`companies`, `vendors`, `requests`, `department_budgets`, `vendor_invoices`).

### 6.4 End-to-End Procurement Workflow
1. Request is approved (`SpendRequest.status=approved_for_execution` or procurement-ready state).
2. User clicks `Convert to PO`.
3. PO draft is auto-filled from request and budget context.
4. Procurement edits line items if needed and issues PO.
5. System posts budget commitment (increase `Committed`).
6. Receiving team logs goods or service receipt.
7. Vendor invoice is attached to PO.
8. Match engine compares PO, receipt, invoice.
9. If pass: mark match approved and allow payment processing.
10. If fail: create exception and block payment until resolved.
11. On payment settlement: reduce `Committed`, increase `Spent`, close PO when fulfilled.

### 6.5 Example
Scenario:
1. Request approved for office laptops: 20 units x 500,000 = 10,000,000
2. PO issued: 10,000,000
3. Receipt recorded: 18 units delivered
4. Invoice submitted: 10,000,000

System result:
1. Quantity mismatch detected
2. Exception created (`over_invoiced_vs_received`)
3. Payment blocked
4. Next action shown: receive remaining units or request corrected invoice

### 6.6 UI Placement
Tenant-side pages:
1. `/procurement/orders`
2. `/procurement/orders/{po}`
3. `/procurement/receipts`
4. `/procurement/match-exceptions`

Tie-ins:
1. `Requests`: button `Convert to PO`
2. `Vendors`: PO and Invoice linkage tabs
3. `Budgets`: show committed column and commitment events
4. `Reports`: procurement lead time, exception rates, commitment aging

## 7) Module B: Treasury and Bank Reconciliation

### 7.1 Objective
Provide cash truth by matching internal payment records to real bank statement activity.

### 7.2 Core Features
1. Bank account registry per tenant
2. Statement import (CSV first, API later)
3. Statement line normalization
4. Auto-match rules for execution payments and direct expense evidence
5. Reconciliation exception queue
6. Manual resolution workflow with reason codes
7. Out-of-pocket handling and reimbursement tracking
8. Cash position and unreconciled exposure dashboards
9. Audit trail for every match and override

### 7.3 Proposed Data Objects (Additive)
1. `bank_accounts`
2. `bank_statements`
3. `bank_statement_lines`
4. `payment_runs`
5. `payment_run_items`
6. `reconciliation_matches`
7. `reconciliation_exceptions`

These tables reference existing attempts and expenses where relevant (`request_payout_execution_attempts`, vendor payment records, expense postings).

### 7.4 End-to-End Treasury Workflow (Execution Payment Stream)
1. Approved payouts and payables are grouped in payment runs.
2. Payment execution is triggered (provider API or manual bank upload path).
3. Statement file is imported daily.
4. Engine matches statement lines to execution records by amount, date, reference, beneficiary.
5. Matched lines are marked reconciled.
6. Unmatched or conflicting lines are sent to reconciliation exceptions.
7. Finance resolves exceptions with typed reason and evidence.
8. Cash and control reports update automatically.

### 7.5 Direct Expense Evidence Reconciliation Workflow (Non-PO Stream)
1. Direct expense is recorded with proof after spend has happened.
2. Expense is posted and budget moves directly to Spent.
3. Statement lines are imported from company bank/card accounts.
4. Engine attempts to match expense records to statement lines using amount/date/merchant/reference.
5. Outcomes:
   - matched: expense gets reconciled status.
   - not_matched: no suitable line found.
   - conflict: multiple possible lines or mismatch.
6. Finance resolves exceptions with reasons such as pending_settlement, duplicate_expense, wrong_amount, wrong_date.

### 7.6 Out-of-Pocket Expenses (Paid Outside Organization Bank Funds)
1. Expense is marked out_of_pocket when staff used personal funds.
2. No company bank-line match is expected at recording time.
3. If reimbursement is approved, reimbursement payout enters execution and is reconciled when bank payment occurs.
4. If reimbursement is rejected, expense remains recorded with final decision and audit reason.

### 7.7 Example
Scenario:
1. Payment run has 50 payouts.
2. Bank statement shows 48 successful debits and 2 reversals.

System result:
1. 48 auto-reconciled.
2. 2 exceptions created with reason `bank_reversal`.
3. Affected requests return to retry/operations path with incident references.
4. Tenant health can show delayed state until resolved.

### 7.8 UI Placement
Tenant-side pages:
1. `/treasury/payment-runs`
2. `/treasury/reconciliation`
3. `/treasury/reconciliation/exceptions`
4. `/treasury/cash-position`

Platform-side (optional later):
1. Cross-tenant reconciliation risk dashboard
2. Exception aging by tenant

## 8) Seamless Integration Rules (No Foundation Break)

### 8.1 Additive Architecture Only
1. Add tables and services, do not remove or rename existing core objects.
2. Existing request and expense routes continue to work unchanged.

### 8.2 Feature Flags and Entitlements
1. Add module keys: `procurement`, `treasury`.
2. Keep both disabled by default per plan until rollout is ready.
3. UI nav and routes follow existing entitlement middleware patterns.

### 8.3 Backward Compatibility
1. Existing tenants can continue with request/expense flows without PO.
2. PO-required policy is configurable by category and threshold.
3. Statement import can start manual (CSV) before any bank API integration.

### 8.4 Audit and Traceability
Every critical step writes `tenant_audit_events`:
1. PO issued, revised, canceled
2. Receipt confirmed
3. Match pass/fail and overrides
4. Statement import and reconciliation actions
5. Exception creation and closure

## 9) Role and Control Matrix
Recommended role mapping:
1. Owner: full configuration and override rights
2. Finance: payment run and reconciliation ownership
3. Manager: request and budget ownership, limited procurement actions
4. Auditor: read-only across procurement and reconciliation trails
5. Staff: request initiation, limited direct expense actions

Sensitive actions requiring owner/finance policy:
1. Match override
2. Reconciliation force-match
3. Exception write-off

## 10) KPIs to Track After Rollout

Procurement KPIs:
1. PO cycle time (request approved -> PO issued)
2. 3-way match pass rate
3. Match exception aging
4. Commitment aging and carry-over

Treasury KPIs:
1. Auto-reconciliation rate
2. Exception backlog and aging
3. Time to close statement period
4. Unreconciled value as percent of monthly outflow

Cross-module KPIs:
1. Budget accuracy (committed/spent variance)
2. Payment success to reconciliation completion time
3. Incident rate tied to mismatch or reconciliation failures

## 11) Implementation Plan (Phased)

### Phase 1: Procurement Foundation
1. Add PO and receipt data model
2. Add request -> PO conversion
3. Add budget commitment postings
4. Add PO UI basics and audit events

### Phase 2: 3-Way Match and Exceptions
1. Add match engine and tolerance rules
2. Add exception queue and resolution flow
3. Add payment block gating for failed match
4. Add procurement reports

### Phase 3: Treasury Foundation
1. Add bank account, statement, and statement line model
2. Add CSV import and normalization
3. Add initial reconciliation screens
4. Add audit events and reconciliation status fields

### Phase 4: Auto-Match and Exception Handling
1. Add rule-based matcher
2. Add exception reason codes and workflows
3. Add reconciliation dashboards and exports
4. Link unresolved exceptions to execution health messaging

### Phase 5: Control Hardening
1. Add policy thresholds for mandatory PO categories/amounts
2. Add maker-checker for overrides
3. Add alerts for stale commitments and recon backlog
4. Add platform oversight cards for exception aging

### Phase 6: Go-Live Enablement
1. Tenant rollout waves by entitlement
2. Migration guidance and runbooks
3. Finance team onboarding and SOPs
4. Post-go-live KPI monitoring and stabilization

## 12) End-to-End Sample Flows

### Sample A: Procurement Spend
1. Marketing submits request for campaign retainer: 5,000,000.
2. Manager + Finance approve.
3. Convert to PO and issue vendor order.
4. Vendor submits invoice.
5. Match passes.
6. Payment executes.
7. Bank statement confirms debit.
8. Reconciliation marks settled.
9. Budget moves from Committed to Spent.

### Sample B: Non-PO Operational Expense (Already Spent)
1. Field officer submits transport expense: 45,000 with receipt after payment happened.
2. Approver validates and posts expense.
3. Budget increases Spent directly.
4. If expense was company card/bank funded, statement line is matched to expense evidence.
5. If expense was out-of-pocket, it is marked out_of_pocket and waits for optional reimbursement decision.
6. If reimbursed, reimbursement payout is reconciled to bank statement.

## 13) What This Delivers for Flowdesk Positioning
After these two modules, Flowdesk supports complete control coverage:
1. Operational governance (requests, approvals, org policy)
2. Financial pre-control (budget + commitment)
3. Procurement discipline (PO and matching)
4. Payment execution control (existing execution engine)
5. Cash truth and close readiness (bank reconciliation)

This is the path to a complete, modern operations and financial control platform.

## 14) Easy User Workflow (What Users See)

### 14.1 Request to PO (Procurement Lane)
1. Approve request.
2. Click Convert to PO.
3. Issue PO.
4. Record receipt.
5. Attach invoice.
6. System runs 3-way match.
7. If matched, payment proceeds.
8. If not matched, exception tells user exactly what to fix.

### 14.2 Direct Expense (Non-PO Lane)
1. Record expense with proof (already spent).
2. Approver posts expense.
3. Budget moves to Spent.
4. System tries to match expense to bank/card statement.
5. If not matched, finance sees clear reason queue.

### 14.3 Out-of-Pocket Case
1. User marks expense as out-of-pocket.
2. Expense is recorded but not bank-reconciled yet.
3. If reimbursement approved, payout is created.
4. Reimbursement payout is reconciled when it hits bank statement.

### 14.4 Common Issue to Resolution Map
1. No bank line found: check statement import date range, then set pending_settlement if still not visible.
2. Amount mismatch: verify charges/fees/partial payment, then split or adjust with approval.
3. Multiple possible lines: finance selects one and records reason.
4. Paid personally: mark out_of_pocket, then approve/reject reimbursement path.
5. Duplicate expense: reject duplicate and keep one valid record with audit trail.

## 15) UI Agreement (Non-Negotiable Build Standard)
The new modules must feel easy, direct, and predictable to use.

### 15.1 UI Principles
1. One clear primary action per screen.
2. Labels must use business language, not internal/system terms.
3. Every exception card must show: what happened, why, and the next action.
4. Required fields first, advanced fields behind optional expand.
5. Status names must be human-readable and consistent across modules.
6. Keep forms short and step-driven.
7. Keep role-specific views focused on what that role needs.

### 15.2 Required Labeling Conventions
1. Use `Needs action`, `Waiting bank match`, `Reconciled`, `Out-of-pocket`, `Reimbursement pending`, `Resolved`.
2. Avoid technical-only labels like `not_matched` and `conflict` in user-facing UI.
3. Always show a helper line under critical status labels.

### 15.3 Required Workflow UX
1. Lane selector must be explicit at start: `PO Procurement` or `Direct Expense`.
2. `Direct Expense` flow must indicate this is post-spend recording.
3. `Out-of-pocket` option must be visible at entry and explain reimbursement path.
4. For failed matching, show guided next steps and one-click route to resolve queue.
5. Every queue row should include a quick action button.

### 15.4 Usability Guardrails
1. No page should require users to interpret internal IDs before they can act.
2. No critical workflow should need more than 3 clicks from list to resolution action.
3. Empty states must explain what to do next.
4. Confirmations should be concise and explicit.

### 15.5 UI Done Criteria
1. New users can complete each major flow without external training docs.
2. Exception resolution path is obvious from list screen.
3. Labels and actions are consistent between procurement and treasury modules.
4. QA checklist includes readability and action clarity checks.
### 15.6 Configurability Rules
1. Settings that affect behavior (thresholds, tolerances, guardrails, policy switches) must be configurable from tenant pages.
2. Avoid unnecessary hardcoding of control-sensitive logic in services.
3. Defaults can exist in config files, but tenant overrides must be supported where business control is required.
4. Configuration changes must be role-protected and auditable.

### 15.7 Modal Pattern Rule
1. If modals are used, they must follow the existing Flowdesk modal template used in current modules.
2. Modal structure, button hierarchy, spacing, validation, and dismissal behavior must remain consistent.
3. New module dialogs must not introduce conflicting modal interaction patterns.
## 16) Code Comment Agreement (Non-Negotiable Build Standard)
All newly created and updated code files for these modules must include clear, purposeful comments.

### 16.1 Comment Scope
1. Add comments where business rules are enforced (matching, blocking, policy gates, reconciliation outcomes).
2. Add comments where decisions are non-obvious (fallbacks, tolerances, overrides, idempotency guards).
3. Add comments on cross-module integration points (budgets, execution, audit logs, reconciliation).
4. Add comments on queue and scheduler behavior that affects operations.

### 16.2 Comment Quality Rules
1. Comments must explain why, not restate obvious code.
2. Keep comments short, direct, and business-readable.
3. Use consistent terms from UI and workflow docs.
4. When a rule can impact money movement, include explicit intent in comment.

### 16.3 Required Comment Areas
1. PO commitment posting and reversal flows.
2. 3-way match pass/fail criteria and payment blocking decisions.
3. Direct expense evidence matching heuristics.
4. Out-of-pocket and reimbursement linkage handling.
5. Reconciliation exception routing and manual resolution boundaries.

### 16.4 Comment Review Gate
1. Pull requests are not complete if critical rule paths have no explanatory comments.
2. QA and reviewer checklist must verify comment clarity in critical financial-control code paths.
## 17) Definition of Done
The modules are considered complete when:
1. Both lanes are live and budget-integrated.
2. PO-required policies are enforceable.
3. 3-way mismatch blocks payment by policy.
4. Bank statements can be imported and reconciled with exception handling.
5. Audit logs and reports cover end-to-end traceability.
6. Tenant and platform dashboards expose health and exception trends.





