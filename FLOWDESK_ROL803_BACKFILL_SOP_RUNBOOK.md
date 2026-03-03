# FLOWDESK_ROL803_BACKFILL_SOP_RUNBOOK

Last updated: 2026-03-03
Owner: Platform Ops + Tenant Finance Owner
Related sprint stories: `ROL-802`, `ROL-803`
Command: `procurement:backfill-vendor-links`

## 1) Purpose
Provide a safe, repeatable procedure to migrate legacy vendor invoice/payment links into procurement controls before full tenant rollout.

This SOP covers:
1. Pre-checks and approval gates.
2. Dry-run interpretation.
3. Apply run execution.
4. Post-run verification.
5. Rollback and incident response.

## 2) Command Reference

Primary command:
```bash
php artisan procurement:backfill-vendor-links --company={TENANT_ID} --dry-run
```

Apply run:
```bash
php artisan procurement:backfill-vendor-links --company={TENANT_ID}
```

Useful options:
1. `--batch=200` batch size.
2. `--date-window=60` invoice vs PO date window in days.
3. `--amount-tolerance=5` allowable invoice-vs-PO variance percent.
4. `--skip-match` skip 3-way match recompute.
5. `--skip-payment-sync` skip vendor payment scope alignment.

## 3) Roles and Approvals

Required roles:
1. Executor: Platform Ops engineer.
2. Approver 1: Tenant Finance Owner.
3. Approver 2: Platform Ops lead.

Approval checkpoints:
1. Before apply run (after dry-run review).
2. After apply run (verification sign-off).

## 4) Pre-Run Checklist

1. Confirm tenant ID and tenant name match.
2. Confirm database backup completed and timestamp recorded.
3. Confirm no ongoing schema migration or bulk import job.
4. Confirm procurement module is enabled for target tenant.
5. Record current baseline counts:
   - unlinked vendor invoices (`purchase_order_id is null`)
   - current open procurement match exceptions
6. Create rollout ticket and paste:
   - executor name
   - tenant ID
   - backup reference
   - planned command

## 5) Dry-Run Procedure

Run:
```bash
php artisan procurement:backfill-vendor-links --company={TENANT_ID} --dry-run
```

Review command output counters:
1. `eligible` invoices with one safe candidate PO.
2. `linked` must remain `0` in dry-run.
3. `ambiguous` invoices with multiple candidate POs.
4. `no_candidate` invoices with no safe candidate.
5. `mismatch_found` vendor payments whose invoice/company/vendor scope disagree.

Decision rules:
1. Proceed when `ambiguous` is low and understood.
2. Pause if `ambiguous` is high or unexpected.
3. Pause if counters look inconsistent with tenant known data.

## 6) Apply Run Procedure

Run:
```bash
php artisan procurement:backfill-vendor-links --company={TENANT_ID}
```

Expected behavior:
1. Links only safe, unambiguous invoice -> PO matches.
2. Recomputes 3-way match state by default.
3. Aligns vendor payment linkage scope by default.
4. Writes tenant audit events:
   - `tenant.procurement.backfill.vendor_invoice_linked`
   - `tenant.procurement.backfill.vendor_payment_synced`
   - `tenant.procurement.backfill.vendor_links_run`

## 7) Post-Run Verification

Minimum checks:
1. Re-run dry-run and verify reduced `eligible` for same tenant.
2. Confirm linked invoices now have `purchase_order_id`.
3. Confirm no unexpected spike in open match exceptions.
4. Confirm audit events exist for this run.

Suggested SQL checks (replace `{TENANT_ID}`):
```sql
select count(*) as unlinked_invoices
from vendor_invoices
where company_id = {TENANT_ID}
  and purchase_order_id is null
  and deleted_at is null;

select action, count(*) as total
from tenant_audit_events
where company_id = {TENANT_ID}
  and action in (
    'tenant.procurement.backfill.vendor_invoice_linked',
    'tenant.procurement.backfill.vendor_payment_synced',
    'tenant.procurement.backfill.vendor_links_run'
  )
group by action;
```

## 8) Rollback Procedure

## 8.1 Immediate Stop Conditions
Stop and escalate if any is true:
1. Tenant data appears cross-scoped.
2. Unexpected large `linked` count.
3. Critical payment gate behavior changes unexpectedly.

## 8.2 Rollback Path A (Preferred): Restore Backup
1. Freeze further backfill runs for affected tenant.
2. Restore pre-run DB backup snapshot.
3. Re-verify tenant counts and reopen rollout only after RCA.

## 8.3 Rollback Path B (Targeted Data Revert)
Use only when full restore is not approved.
1. Identify affected rows by audit events from this run.
2. Revert only touched records:
   - `vendor_invoices.purchase_order_id` back to `null` where linked by backfill event.
   - `vendor_invoice_payments.vendor_id/company_id` back to pre-run values from backup/audit evidence.
3. Re-run dry-run to confirm state stabilized.
4. Document incident ID and corrective actions.

Note: Targeted rollback requires pre-run backup evidence for exact prior values.

## 9) Incident and Escalation

If failure occurs:
1. Open incident with severity and tenant impact.
2. Attach:
   - command used
   - full console output
   - audit event IDs
   - verification queries
3. Notify Platform Ops lead and tenant finance owner.
4. Track closure with RCA and preventive action.

## 10) Run Log Template

Use this in rollout tickets:

```text
Tenant:
Executor:
Backup ref/time:
Dry-run command:
Dry-run summary:
Approval to apply (names/time):
Apply command:
Apply summary:
Post-check summary:
Rollback needed? (yes/no):
Incident ID (if any):
Final sign-off:
```
