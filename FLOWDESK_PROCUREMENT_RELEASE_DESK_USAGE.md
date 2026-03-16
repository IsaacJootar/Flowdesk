# Flowdesk Procurement Workspace Usage

Last updated: 2026-03-16

This guide covers the unified procurement operations workspace.

## Main entry
- Route: `/procurement/release-desk`
- UI title: `Manage Procurement Workspace`
- Sidebar label: `Manage Procurement`
- Supported roles: owner, finance, manager, auditor

## Workspace design
The page is now organized into 5 execution lanes:
1. `Approved Requests (Need PO)`
2. `PO Drafts (Need Issue)`
3. `Issued POs (Need Receipt)`
4. `Invoices/Match (Need Resolve)`
5. `Ready for Payout Handoff`

Each row shows exactly one `Next Action` button.

## What changed
- Procurement is no longer presented as one mixed table.
- Operators can see bottlenecks at a glance via:
  - lane counters
  - top workload progress bar
  - bottleneck label (`Current bottleneck: ...`)

## End-to-end workflow
1. Request gets approved.
2. Convert request to PO.
3. Issue PO.
4. Record goods receipt.
5. Link vendor invoice.
6. Resolve match exceptions.
7. Move ready rows to payout queue.

## Plain language statuses
- `Approved - Need PO`
- `Draft - Waiting for Issue`
- `Waiting for Receipt`
- `Waiting for Invoice`
- `Exception to Resolve`
- `Ready for Payout`

## Next-action routing
- Convert to PO -> `/requests` (opens request context)
- Issue PO / Record Receipt / Link Invoice -> `/procurement/orders`
- Resolve Exception -> `/procurement/match-exceptions`
- Run Payout -> `/execution/payout-ready`

## Scope and tenancy guardrails
- All workspace queries are company-scoped.
- Rows with scope mismatch are dropped and logged.
- Missing linked request scope metadata is treated as invalid row context, not auto-corrected.

## Drill-down pages
- `/procurement/orders`
- `/procurement/receipts`
- `/procurement/match-exceptions`
- `/procurement/release-help`

Use these for detailed handling; the workspace remains the primary execution surface.

## Match Exceptions Flow Agent Workflow

Page:
- Tenant route: `/procurement/match-exceptions`

Purpose:
- Provide advisory guidance for exception triage without replacing operator decisions.
- Explain `why blocked`, highlight risk level, and recommend the most direct next fix action.

How to use:
1. Open `/procurement/match-exceptions`.
2. Click `Use Flow Agent` on the target exception row.
3. Review `Flow Agent` output (`risk`, `why blocked`, `next action`).
4. Apply `Resolve` or `Waive` only after confirming evidence and maker-checker policy.

Guardrails:
- Flow Agent appears only when tenant `ai_enabled` entitlement is on.
- Flow Agent is advisory-only; it does not auto-resolve exceptions.
- Analysis action is audited as `tenant.procurement.match.exception.flow_agent_analyzed`.
