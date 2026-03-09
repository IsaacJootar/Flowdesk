# FLOWDESK_AI_PLAN.md

Last updated: 2026-03-09

## 1) Goal
Add practical AI features that make Flowdesk faster and safer for operations and finance teams, without expensive subscriptions.

Primary outcome:
- Faster approvals and reconciliation
- Better exception handling
- Better operator clarity
- No tenant data mixing

Current rollout status (2026-03-09):
- Requests workflow now exposes AI as **Flow Agents** (advisory side panel in draft and request view modals).
- Flow Agents is tenant-gated via `ai_enabled` entitlement and remains human-in-control (`advisory_only` guard respected).
- Flow Agents now supports user-triggered workflow execution in Requests (for example: convert/create expense) when the user explicitly clicks the action.
- Redundant agent-submit and agent-approve actions were removed; Requests Flow Agents now focuses on higher-value actions (convert-to-PO, create-expense) plus risk analysis.
- Initial implementation is `app/Services/AI/RequestFlowAgentService.php` with feature coverage in `tests/Feature/Requests/RequestFlowAgentsTest.php`.

## 2) Non-Negotiable Rules

1. Tenant scope lock:
- Every AI read/write must be filtered by `company_id`.
- No cross-tenant indexing, retrieval, or prompt context.

2. Human-in-control:
- AI suggests, humans decide.
- No auto-final approval, auto-payment, or auto-waive for high-risk actions.

3. Queue-first processing:
- Heavy AI work runs in queues, not request/response.
- Jobs must be idempotent and retry-safe.

4. Clear labels in UI:
- Use plain wording.
- Show confidence and one clear next action.

5. Full audit trail:
- Log AI recommendations, user decisions, and overrides.

## 3) Low-Cost AI Stack (Use Now)

No expensive subscriptions required.

1. Local LLM runtime:
- Ollama

2. Local models:
- `qwen2.5:7b-instruct` (primary)
- `llama3.1:8b-instruct` (fallback)
- `phi3:mini` (fast lightweight tasks)

3. Embeddings:
- `nomic-embed-text` via Ollama

4. Vector store:
- Qdrant (self-hosted)

5. OCR and parsing:
- Tesseract OCR
- OCRmyPDF

6. ML for anomaly scoring:
- scikit-learn (IsolationForest and rule blending)

7. Service boundary:
- Small FastAPI AI service called by Laravel

## 4) Architecture Pattern

1. Laravel modules trigger AI jobs.
2. Jobs call internal AI service endpoints.
3. AI service uses tenant-scoped retrieval (Qdrant collections partitioned by `company_id`).
4. AI response saved to module-specific suggestion tables.
5. UI shows suggestion + confidence + action buttons (`Accept`, `Edit`, `Ignore`).
6. Final action by user writes normal business records and audit events.

## 5) AI Features by Module

## 5.1 Requests and Approvals
Feature: Flow Agents (Requests)
- What it does:
  - Summarizes request context, policy checks, and risk signals.
  - Suggests `Approve`, `Return`, or `Escalate` with reasons.
- Where:
  - Requests draft modal (side panel)
  - Requests details modal (side panel)
- Clear labels:
  - `Flow Agents`
  - `Recommendation`
  - `Why this suggestion`
  - `Confidence`

## 5.2 Expenses
Feature: Receipt Assistant
- What it does:
  - Extracts vendor, date, amount, and receipt number.
  - Suggests spend category.
- Where:
  - Expense upload and details
- Clear labels:
  - `Extracted amount`
  - `Suggested category`
  - `Apply to expense`

Feature: Duplicate Spend Guard
- What it does:
  - Flags likely duplicate receipts or repeated claims.
- Where:
  - Expenses list and review panel
- Clear labels:
  - `Possible duplicate`
  - `Review before approve`

## 5.3 Procurement
Feature: Match Assistant
- What it does:
  - Explains why 3-way match failed and suggests exact fix path.
- Where:
  - Procurement match exceptions
- Clear labels:
  - `Why blocked`
  - `Suggested fix`
  - `Next action`

## 5.4 Treasury
Feature: Reconciliation Assistant
- What it does:
  - Suggests best statement-to-transaction matches.
  - Ranks confidence and explains rule signals.
- Where:
  - Treasury reconciliation and exceptions
- Clear labels:
  - `Suggested match`
  - `Confidence`
  - `Accept match`

## 5.5 Execution and Payouts
Feature: Payout Risk Check
- What it does:
  - Scores payout risk before operator runs payout.
  - Highlights drift, repeated failures, and unusual amounts.
- Where:
  - Execution payout-ready page
- Clear labels:
  - `Risk level`
  - `Top risk reason`
  - `Proceed with caution`

## 5.6 Vendors
Feature: Vendor Health Summary
- What it does:
  - Summarizes invoice delays, mismatch frequency, and payout retry patterns.
- Where:
  - Vendor details and payables desk
- Clear labels:
  - `Vendor health`
  - `Common issues`
  - `Recommended follow-up`

## 5.7 Platform Operations
Feature: Incident Narrator
- What it does:
  - Converts technical incident logs into plain-language summaries for ops.
- Where:
  - Platform incident history and operations hub
- Clear labels:
  - `What happened`
  - `Impact`
  - `Recommended next step`

## 6) Rollout Plan

## Phase A: Foundation (2-3 weeks)
1. Deploy local AI stack (Ollama, Qdrant, FastAPI service).
2. Add tenant-safe AI service contracts and audit schema.
3. Add feature flags per tenant for each AI feature.

Gate to move on:
- Tenant isolation tests pass.
- Queue reliability tests pass.

## Phase B: Quick Wins (3-4 weeks)
1. Receipt Assistant (Expenses)
2. Approval Copilot (Requests)
3. Match Assistant hints (Procurement)

Gate to move on:
- At least 80 percent operator acceptance in pilot tenants.
- No P1 tenant-scope or data leakage issues.

## Phase C: Control Intelligence (3-4 weeks)
1. Reconciliation Assistant (Treasury)
2. Payout Risk Check (Execution)
3. Vendor Health Summary

Gate to move on:
- Reduced exception resolution time and fewer retries.

## Phase D: Ops Narrative and Optimization (2-3 weeks)
1. Incident Narrator (Platform)
2. KPI dashboards for AI effectiveness
3. Prompt and confidence tuning

## 7) UX Agreement for AI Screens

Every AI card must show these in this order:
1. `Suggestion`
2. `Why`
3. `Confidence`
4. `Action`

Required action buttons:
- `Accept`
- `Edit`
- `Ignore`

Do not show raw model text blobs or technical prompt payloads to users.

## 8) Safety and Governance

1. Do not let AI directly approve payments or bypass controls.
2. Keep strict role checks for overrides.
3. Store AI prompts/responses in tenant-scoped audit records.
4. Add periodic review for false positives and false negatives.

## 9) Cost Controls

1. Default all AI to local models.
2. Use smaller models first; escalate only if confidence is low.
3. Cache repeated embedding/search outputs where safe.
4. Run heavy scoring jobs off-peak.

## 10) Definition of Done for AI Program

AI rollout is complete when:
1. Core AI features are live in Requests, Expenses, Procurement, Treasury, Execution, Vendors, and Platform Ops.
2. All AI outputs are tenant-scoped and audited.
3. Operators can complete key tasks faster with measurable reduction in resolution time.
4. UI language remains business-friendly and clear.
5. High-risk actions remain human-approved.
