# Flowdesk Scale Validation Runbook

## Objective
Validate that the first production rollout can handle realistic queue, reporting, and reconciliation load.

## Priority Flows
- Request lifecycle desk
- Reports center
- Vendor command center
- Treasury auto-reconciliation
- Billing and payout queue processing

## Test Method
1. Seed representative tenant data for small, medium, and large tenants.
2. Measure page render time, query count, queue drain time, and failure rate.
3. Run each flow with cold cache and warm cache.
4. Repeat with concurrent queue workers enabled.

## Metrics To Capture
- P95 page response time
- Slowest SQL query per flow
- Queue backlog growth during load
- Auto-recovery completion time
- Failed job count during and after the run

## Pass Criteria
- No tenant boundary regressions
- No blocking errors in logs
- No unbounded memory growth
- Queue backlog returns to baseline after load
- Finance-critical pages stay within agreed response targets

## Follow-Up
- Record findings in the release notes
- Open fixes for any flow that exceeds targets
- Re-run the benchmark after each performance fix
