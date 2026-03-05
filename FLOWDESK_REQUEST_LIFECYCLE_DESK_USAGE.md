# Flowdesk Request Lifecycle Desk Usage

Last updated: 2026-03-05

## Purpose

The Request Lifecycle Desk (`/requests/lifecycle-desk`) is the single operations page to move approved requests from procurement handoff to payout dispatch and closure.

## Lanes and next action

1. Approved (Need PO)
- Meaning: Request is approved but has no linked purchase order.
- Next action: `Convert to PO`.

2. PO / Match Follow-up
- Meaning: PO exists, but procurement work is still incomplete or blocked by procurement gate checks.
- Next action: `Open Procurement` or `Resolve Exception`.

3. Approved for Execution (Waiting Payout Dispatch)
- Meaning: Request is `approved_for_execution` and not procurement-blocked.
- Next action: `Run Payout`.

4. Execution Active / Retry
- Meaning: Request is in `execution_queued`, `execution_processing`, or `failed`.
- Next action: `Re-check Queue` or `Rerun Payout`.

5. Settled / Reversed Outcomes
- Meaning: Request payout has reached a closed state.
- Next action: `Open Request`.

## Operator flow

1. Start with lane 1 and clear `Need PO` rows.
2. Clear lane 2 procurement blockers and match exceptions.
3. Dispatch rows in lane 3 through payout queue.
4. Monitor/retry rows in lane 4.
5. Audit results in lane 5.

## Scope and access

- Tenant scope: every row is filtered by the signed-in user company.
- Role scope:
  - owner, finance, auditor: tenant-wide visibility.
  - manager: department + own requests.
- Staff users are intentionally blocked from this page.

## UI links

- Main desk: `/requests/lifecycle-desk`
- Help page: `/requests/lifecycle-help`
- Payout queue: `/execution/payout-ready`
- Procurement workspace: `/procurement/release-desk`
