# Flowdesk Treasury Daily Reconciliation Desk Usage

Last updated: 2026-03-04

This guide is the daily operations runbook for treasury reconciliation in one workspace.

## Main entry
- Route: `/treasury/reconciliation`
- Sidebar label: `Manage Treasury`
- Help page: `/treasury/reconciliation/help`
- Roles with access: owner, finance, manager, auditor
- Roles that can import/reconcile: owner, finance
- Roles that can resolve/waive exceptions: tenant-configured in Treasury Controls (`exception_action_allowed_roles`)

## What the desk includes
- Import status for the active statement
- Statement/unmatched line monitor
- Open exception queue preview with inline resolve/waive
- Auto-reconcile action
- Payment-run context (processing/failed)
- Close-day checklist with actionable links

## Daily operating flow
1. Select bank account and statement scope.
2. Import the day statement file.
3. Run auto-reconcile once import completes.
4. Review unmatched lines and open exceptions.
5. Resolve/waive exceptions with clear notes.
6. Confirm close-day checklist is fully done.

## Close-day checklist interpretation
- Statement imported for today: required before signoff.
- Auto-reconcile run completed: ensures line classification is current.
- Exception queue cleared or triaged: open exceptions must be justified or closed.
- Unreconciled backlog within threshold: uses tenant threshold (`reconciliation_backlog_alert_count_threshold`).
- No payment runs stuck in processing: avoid unresolved settlement states at close.

## Inline exception actions
- `Resolve`: issue fixed/validated, exception closed.
- `Waive`: risk accepted with explicit rationale.
- Every action requires a resolution note for audit trail.
- If maker-checker is enabled, creator cannot close the same exception.

## Incident handling playbook
### Import incident
- Symptom: import fails or no rows imported.
- Check: CSV header (`posted_at`, `direction`, `amount`), row limits, file format.
- Action: correct file, re-import, re-run auto-reconcile.

### Unmatched spike incident
- Symptom: unmatched lines suddenly high.
- Check: statement scope, account selection, provider references, date/amount consistency.
- Action: run auto-reconcile, inspect top unmatched references, triage exception queue.

### Exception closure blocked
- Symptom: cannot resolve/waive.
- Check: role permissions and maker-checker policy.
- Action: handoff to authorized user, include statement reference + exception code + line reference.

### Processing run incident
- Symptom: payment runs stuck processing.
- Check: `/treasury/payment-runs` and execution health for linked signals.
- Action: clear stuck states before close-day signoff.

## Handoff template (short)
- Statement: `<statement_reference>`
- Line: `<line_reference>`
- Exception: `<exception_code>`
- Severity/stream: `<severity> / <match_stream>`
- Action taken: `resolve|waive|pending`
- Note: `<what was validated and next owner>`

## Related pages
- Exception queue: `/treasury/reconciliation/exceptions`
- Payment runs: `/treasury/payment-runs`
- Cash position: `/treasury/cash-position`
- Controls: `/settings/treasury-controls`