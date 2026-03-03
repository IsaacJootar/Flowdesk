# FLOWDESK_NEXT_MODULES_SPRINT_PLAN.md

Last updated: 2026-03-03
Scope source: `FLOWDESK_NEXT_MODULES_BLUEPRINT.md`
Planning horizon: 8 implementation sprints + hardening/release
Cadence assumption: 2-week sprints

## 1) Objective
Turn the module blueprint into execution-ready delivery work that can be planned, assigned, built, tested, and rolled out without breaking current Flowdesk foundations.

Target modules:
1. Commitments and Procurement Control (PO + 3-way match)
2. Treasury and Bank Reconciliation

## 2) Delivery Principles
1. Additive only: no breaking schema/route changes to existing modules.
2. Feature-flagged rollout: `procurement` and `treasury` entitlements.
3. Tenant safety first: clear permissions, audit trail, and exception controls.
4. Budget integrity: strict handling of `Allocated`, `Committed`, `Spent`, `Available`.
5. Operational observability: metrics, incident visibility, and reconciliation traceability.

## 2.1 UI Agreement Implementation Rules
1. Use business-first labels and consistent status vocabulary.
2. Build lane-first entry UX (`PO Procurement` vs `Direct Expense`).
3. Every exception must show one clear next action.
4. Keep primary actions visible from list views.
5. Reject technical status keys in user-facing copy.
6. Include UX clarity checks in QA sign-off.
## 2.2 Code Comment Agreement Implementation Rules
1. All new or modified critical logic must include clear rule comments.
2. Comments must explain why a control exists, not just what code does.
3. Financial-control paths (match, block, reconcile, override) require explicit intent comments.
4. Reviewer checklist must include code-comment clarity for high-risk paths.
5. No sprint closure for relevant stories without comment-quality sign-off.
## 2.3 Configurability and UI Pattern Rules
1. No unnecessary hardcoding of tenant-operational thresholds, tolerances, or guardrails.
2. Add tenant-config pages for settings that require scope-level control.
3. Defaults may come from config files, but tenant overrides are required for control-sensitive settings.
4. Modal dialogs must use the existing Flowdesk modal design template and behavior conventions.
5. Configuration changes must be audited and role-protected.
6. Release gate includes proof that configurable settings are exposed in UI where required.
## 3) Epic Map

### EPIC P1: Procurement Foundation
Goal: Introduce PO lifecycle and budget commitments tied to approved requests.

### EPIC P2: 3-Way Match and Procurement Exceptions
Goal: Block risky payment scenarios and provide controlled exception resolution.

### EPIC T1: Treasury Foundation and Statement Ingestion
Goal: Model bank accounts/statements and ingest statement data safely.

### EPIC T2: Reconciliation Engine and Exception Workbench
Goal: Auto-match transactions and operationalize unresolved exceptions.

### EPIC X1: Cross-Cut Governance, Reporting, and Rollout
Goal: Entitlements, controls, reporting, migration, and release hardening.

## 4) Sprint-by-Sprint Plan

## Sprint 1: Data Foundations (Procurement + Treasury)
Primary focus: schema and base domain models.

Stories:
1. `PROC-101` Create procurement core tables.
2. `PROC-102` Add PO and receipt Eloquent models and relations.
3. `BUD-101` Add commitment posting model/service skeleton.
4. `TRSY-101` Create treasury core tables (`bank_accounts`, `bank_statements`, `bank_statement_lines`).
5. `TRSY-102` Add reconciliation domain models and base statuses.

Acceptance criteria:
1. Migrations run clean on fresh DB.
2. Existing module tests remain green.
3. New models support tenant scoping (`company_id`) and soft/audit metadata where required.
4. No existing routes or pages regress.

Dependencies:
1. Existing `Company`, `Vendor`, `SpendRequest`, `DepartmentBudget` relations.

## Sprint 2: Request to PO Conversion + Commitment Posting
Primary focus: create operational entry point into procurement lane.

Stories:
1. `PROC-201` Add `Convert to PO` action on approved request flow.
2. `PROC-202` Build PO draft creation service from approved request payload.
3. `BUD-201` Post commitment on PO issue (`Committed` increase).
4. `AUD-201` Log PO issue and commitment events to `tenant_audit_events`.
5. `UI-201` Add procurement nav and PO list/detail tenant pages (feature-flagged).

Acceptance criteria:
1. Only policy-allowed roles can convert to PO.
2. PO draft is prefilled from request and budget context.
3. Issued PO updates budget commitment values correctly.
4. Audit trail contains actor, action, metadata, timestamp.

Dependencies:
1. Approval status compatibility in `SpendRequest`.
2. Entitlement key `procurement` added and enforced.

## Sprint 3: Goods Receipt + Vendor Invoice Linking
Primary focus: complete the PO document chain.

Stories:
1. `PROC-301` Add goods receipt create/edit workflow.
2. `PROC-302` Link vendor invoice records to POs.
3. `PROC-303` Compute receipt balances at PO line-item level.
4. `UI-301` Add receipts list/detail and PO timeline UI sections.

Acceptance criteria:
1. Receipt cannot exceed PO quantity unless override policy allows.
2. Invoice can be linked to PO and line-level references stored.
3. PO state transitions update correctly (`issued`, `part_received`, `received`, `invoiced`).
4. Tenant users see only their own records.

## Sprint 4: 3-Way Match Engine + Payment Blocking
Primary focus: automated control gate before payment.

Stories:
1. `MATCH-401` Implement 3-way match service (PO vs receipt vs invoice).
2. `MATCH-402` Add tolerance configuration (qty/amount/date windows).
3. `MATCH-403` Create match result and exception records.
4. `EXEC-401` Gate payment execution when match fails.
5. `AUD-401` Log match pass/fail and blocked-payment actions.

Acceptance criteria:
1. Match outcomes are deterministic and reproducible.
2. Failed match prevents payment progression by policy.
3. Exception row includes reason code and actionable resolution hint.
4. Successful match allows normal existing execution flow.

Dependencies:
1. Existing payout execution orchestration and queue processing.

## Sprint 5: Treasury Import + Manual Reconciliation Workbench
Primary focus: statement ingest and human-in-the-loop reconciliation.

Stories:
1. TRSY-501 Add bank account management page and settings.
2. TRSY-502 Build statement CSV import and parser validation.
3. TRSY-503 Store normalized statement lines and import batch metadata.
4. RECON-501 Build manual match/unmatch UI and reason capture.
5. RECON-504 Add direct expense evidence reconciliation statuses (matched, not_matched, conflict).
6. RECON-505 Add out-of-pocket classification and reimbursement linkage.
7. AUD-501 Log all reconciliation manual actions.

Acceptance criteria:
1. Invalid statement file formats are rejected with clear errors.
2. Imported lines are idempotent per account/date/reference safeguards.
3. Finance role can resolve unmatched lines with mandatory reason.
4. Direct expense records can be reconciled against bank/card lines.
5. Out-of-pocket expenses are excluded from bank matching until reimbursement payout exists.
6. Reconciliation actions are fully auditable.

## Sprint 6: Auto-Match Rules + Exception Queues
Primary focus: automation and operational queueing.

Stories:
1. RECON-601 Implement auto-match rules (reference, amount, date, beneficiary).
2. RECON-602 Add confidence scoring and match statuses.
3. RECON-603 Build reconciliation exceptions queue and aging indicators.
4. RECON-604 Add direct-expense matching heuristics (merchant text similarity + date window).
5. EXEC-601 Integrate unresolved reversals/failures with execution retry/incident pathways.
6. RPT-601 Add reconciliation metrics to reports center.

Acceptance criteria:
1. Auto-match rate measurable and configurable.
2. Exception queue prioritizes by age and value.
3. Reversal/failed settlement exceptions can trigger operational follow-up.
4. Direct expense exceptions show clear reason and next action.
5. Reports expose reconciled vs unreconciled value.

## Sprint 7: Governance Hardening and Controls
Primary focus: policy guardrails and control maturity.

Stories:
1. `POL-701` Add mandatory-PO policy rules by category and amount threshold.
2. `POL-702` Add maker-checker control for override actions.
3. `POL-703` Add write-off and force-match authorization guardrails.
4. `ALRT-701` Add stale commitment and recon backlog alerts.
5. `UI-701` Add role-specific dashboards for finance/owner/auditor.

Acceptance criteria:
1. Non-PO route blocked when policy requires PO.
2. Sensitive overrides require designated roles.
3. Alert conditions are visible in tenant and platform operational views.
4. Audit logs include before/after policy decisions.

## Sprint 8: Tenant Rollout, Migration, and Enablement
Primary focus: safe adoption and go-live readiness.

Stories:
1. `ROL-801` Add entitlements for `procurement` and `treasury` modules.
2. `ROL-802` Build migration scripts/backfill for existing vendor invoice/payment links.
3. `ROL-803` Publish SOPs, runbooks, and tenant onboarding docs.
4. `ROL-804` Add KPI dashboard cards for new modules.
5. `ROL-805` Conduct pilot rollout with phased tenant cohorts.

Acceptance criteria:
1. Modules can be enabled tenant-by-tenant without regressions.
2. Pilot tenants complete one full period close with reconciliation.
3. KPI baseline captured and compared post rollout.
4. Support runbooks validated by operations team.

## 5) Story Template (Use for Jira/Linear)
Use this template for each story:

1. Story ID: `PROC-xxx` / `TRSY-xxx` / `MATCH-xxx` / `RECON-xxx`.
2. Title: concise outcome-focused title.
3. Type: Feature / Tech / Refactor / Test / Docs.
4. Description: business value + technical scope.
5. In scope: concrete list.
6. Out of scope: explicit exclusions.
7. Acceptance criteria: testable bullet points.
8. Dependencies: story IDs and external constraints.
9. Risks: what can go wrong.
10. Test plan: unit + feature + regression.
11. Rollback plan: disable flags, migration rollback, queue drains.

## 6) Testing Strategy by Epic

Procurement tests:
1. Request-to-PO conversion rules.
2. Commitment posting accuracy under concurrent actions.
3. PO/receipt/invoice lifecycle transitions.
4. Match pass/fail scenarios and payment gate behavior.

Treasury tests:
1. Statement import parser robustness and idempotency.
2. Auto-match precision and false-positive prevention.
3. Direct expense evidence matching (matched/not_matched/conflict).
4. Out-of-pocket handling and reimbursement linkage.
5. Exception resolution permissions and audit logs.
6. Reconciliation totals consistency.

Regression tests:
1. Existing execution ops center workflows.
2. Existing request and expense approval flows.
3. Existing budget reports and summaries.
4. Existing tenant entitlement behavior.

## 7) Data and Migration Safeguards
1. All new tables include `company_id` and index strategy.
2. Large imports processed in queued jobs with progress state.
3. Idempotency keys used for imports and auto-match batches.
4. Migration order guarantees no foreign-key deadlocks.
5. Backfill jobs are resumable and auditable.

## 8) Non-Breaking Integration Checklist
Before each release:
1. Existing routes still return expected responses.
2. Existing seeded demo tenants still function without new module enablement.
3. Budget calculations unchanged where no PO commitments exist.
4. Execution services not coupled to new modules unless feature enabled.
5. Platform operations screens remain stable and filterable.

## 9) Release Gates
Go/no-go criteria per wave:
1. Zero critical regressions in requests/expenses/execution.
2. Procurement match failure handling verified.
3. Reconciliation exception queue operable by finance users.
4. KPI tracking visible for pilot tenants.
5. Support team runbook rehearsal completed.

## 10) Suggested Team Allocation
1. Squad A (Procurement): PO lifecycle, receipts, matching.
2. Squad B (Treasury): import, auto-match, exceptions, dashboards.
3. Platform/Shared: entitlements, audit, reporting, rollout tooling.
4. QA/Controls: regression suite + policy validation + UAT support.

## 11) Priority Order If Capacity Is Tight
If only one module can start first:
1. Start Procurement first if uncontrolled vendor spend is current risk.
2. Start Treasury first if month-end close/reconciliation pain is current risk.
3. Ideal: start both foundations in parallel (Sprint 1), then sequence heavy logic.

## 12) Definition of Program Completion
Program is complete when:
1. Both lanes (PO and non-PO) are budget-integrated and policy-enforced.
2. Payment flow can be blocked by failed match where required.
3. Statement reconciliation can close monthly periods with traceable exceptions.
4. Tenant and platform users have actionable operational visibility.
5. Post-rollout KPIs show measurable control improvement.




## 13) UI Agreement Story Traceability
To enforce the UI agreement in delivery, these stories are mandatory:
1. `UI-101` Shared UX copy dictionary and status language.
2. `UI-102` Lane-first navigation and screen map standard.
3. `UI-202` Lane selector UX with helper text and safe defaults.
4. `UI-502` Reconciliation exception rows with guided next actions.
5. `UI-702` Final UX clarity pass to remove technical labels.

Release gate addition:
1. No sprint closes if user-facing screens still expose technical status keys (`not_matched`, `conflict`) without plain-language labels.
2. Exception queues must provide one-click next action from list view.

## 14) Code Comment Story Traceability
To enforce comment clarity during implementation, these stories are mandatory:
1. `ENG-101` Define code-comment standards for procurement and treasury modules.
2. `ENG-102` Add reviewer checklist for critical financial-control code paths.
3. `ENG-601` Audit comment coverage in reconciliation and matching services.
4. `QA-801` Validate comment clarity during release-readiness review.

Release gate addition:
1. No release if critical business-rule paths are uncommented or ambiguously commented.
2. High-risk financial logic must include intent-level comments before merge.
## 15) Config and UI Pattern Story Traceability
To enforce configurability and UI consistency, these stories are mandatory:
1. `CFG-101` Define tenant-config matrix for thresholds, tolerances, and guardrails.
2. `CFG-201` Build tenant settings pages for procurement and treasury controls.
3. `CFG-202` Wire runtime services to tenant-scoped settings with safe defaults.
4. `UI-103` Define and reuse Flowdesk modal template for all new module dialogs.
5. `UI-503` UX review to ensure new modals and forms follow existing UI pattern.

Release gate addition:
1. No release if control-sensitive behavior remains hardcoded without tenant-configurable override.
2. No release if new modals deviate from standard Flowdesk modal template.

## 16) Current Build Progress (2026-03-03)
1. Sprint 1 foundations: completed.
2. Sprint 2 request-to-PO + commitment posting: completed.
3. Sprint 3 receipts + invoice linking: completed.
4. Sprint 4 3-way match + payout gate: completed.
5. Sprint 5 treasury import + reconciliation workbench: completed.
6. Sprint 6 automation/reporting: completed.
   - Done: auto-match engine, confidence-scored candidate selection, exception queue aging prioritization, reports-center treasury metrics.
   - Done: direct-expense heuristic tuning (merchant text similarity + date window + confidence floor) with tenant controls.
   - Done: reversal/failure handoff workflow into treasury exceptions with incident linking for payout, billing, and webhook pipelines.
7. Sprint 7 and Sprint 8 remain open for policy hardening, rollout controls, and enablement operations.

