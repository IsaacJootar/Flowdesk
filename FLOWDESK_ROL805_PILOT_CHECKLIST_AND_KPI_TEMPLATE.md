# FLOWDESK_ROL805_PILOT_CHECKLIST_AND_KPI_TEMPLATE

Last updated: 2026-03-03
Owner: Platform Ops + Finance Operations
Related sprint stories: `ROL-805`

## 1) Purpose
Provide a practical pilot rollout checklist and KPI baseline template for procurement + treasury enablement.

This document is for:
1. Selecting pilot tenants.
2. Running phased pilot waves.
3. Measuring before/after outcomes.
4. Making go/no-go rollout decisions.

## 2) Pilot Cohort Structure

Recommended waves:
1. Wave 1: 1-2 low-risk tenants.
2. Wave 2: 2-4 medium-complexity tenants.
3. Wave 3: broader rollout after sign-off.

Tenant selection criteria:
1. Active finance owner available.
2. Stable monthly transaction volume.
3. Historical vendor invoice and payment data quality acceptable.
4. No unresolved critical incidents at start date.

## 3) Pre-Pilot Tenant Checklist

For each tenant:
1. Confirm tenant modules: `procurement`, `treasury` enabled.
2. Confirm finance and owner roles are assigned and active.
3. Confirm controls configured in:
   - `/settings/procurement-controls`
   - `/settings/treasury-controls`
4. Run backfill dry-run:
   - `php artisan procurement:backfill-vendor-links --company={TENANT_ID} --dry-run`
5. Complete `ROL-803` approval gate and apply run if approved.
6. Verify key pages load:
   - `/procurement/orders`
   - `/procurement/receipts`
   - `/procurement/match-exceptions`
   - `/treasury/reconciliation`
   - `/treasury/reconciliation/exceptions`
   - `/execution/health`

## 4) Pilot Operations Checklist

Daily (week 1):
1. Review new procurement exceptions and age buckets.
2. Review treasury reconciliation exceptions and age buckets.
3. Check execution health status for affected pipelines.
4. Log incidents and resolution turnaround.

Twice weekly (after week 1):
1. Compare KPI trend vs baseline.
2. Review blocked payout handoffs from procurement gate.
3. Review manual overrides and denied sensitive actions.

## 5) KPI Baseline Window

Use two equal windows:
1. Baseline window: 14 days before pilot start.
2. Pilot window: first 14 days after enablement.

If tenant volume is low, use 30-day windows.

## 6) KPI Definition Template

Track these KPIs per tenant:

| KPI ID | KPI Name | Formula | Source | Direction |
|---|---|---|---|---|
| P1 | 3-way match pass rate | `matched / (matched + mismatch)` | `invoice_match_results` | Up |
| P2 | Open procurement exceptions | `count(open exceptions)` | `invoice_match_exceptions` | Down |
| P3 | Procurement exception aging | `avg(hours open)` | `invoice_match_exceptions` | Down |
| T1 | Auto-reconciliation rate | `auto matched / total statement lines` | `reconciliation_matches`, `bank_statement_lines` | Up |
| T2 | Open treasury exceptions | `count(open exceptions)` | `reconciliation_exceptions` | Down |
| T3 | Treasury exception aging | `avg(hours open)` | `reconciliation_exceptions` | Down |
| X1 | Payouts blocked by procurement gate | `count(blocked audits)` | `tenant_audit_events` | Down |
| X2 | Manual override volume | `count(resolve/waive actions)` | `tenant_audit_events` | Down/Stable |
| X3 | Incident rate for control failures | `incidents / week` | incident history + audits | Down |

## 7) KPI Capture Sheet (Copy Template)

```csv
date,tenant_id,tenant_name,kpi_id,kpi_name,baseline_value,pilot_value,variance_pct,status,notes
2026-03-03,101,Acme Ltd,P1,3-way match pass rate,0.62,0.78,25.81,improved,
2026-03-03,101,Acme Ltd,P2,Open procurement exceptions,19,8,-57.89,improved,
2026-03-03,101,Acme Ltd,T1,Auto-reconciliation rate,0.44,0.67,52.27,improved,
```

Status values:
1. `improved`
2. `flat`
3. `regressed`
4. `needs_review`

## 8) Go/No-Go Decision Gates

Go to next wave only if all apply:
1. No unresolved critical incident.
2. No tenant data-scope breach.
3. P1 and T1 do not regress materially.
4. P2 and T2 trend downward or are stable with mitigation plan.
5. Ops + finance sign-off recorded.

Hold rollout if any apply:
1. Critical incident unresolved > 24h.
2. Exception backlog grows without recovery.
3. Repeated failed backfill runs or data inconsistency.

## 9) Pilot Review Meeting Template

Use this agenda per wave:
1. KPI summary by tenant.
2. Incident summary and RCA status.
3. Exception queue trend review.
4. User feedback from finance/owner.
5. Decision: continue, pause, or rollback partial scope.

## 10) Evidence Pack Checklist

For each tenant pilot pack include:
1. Backfill dry-run output.
2. Backfill apply output.
3. Post-run verification snapshot.
4. KPI baseline sheet and pilot sheet.
5. Incident list and actions.
6. Final go/no-go approval record.

## 11) In-App Capture Tooling

Use either path:
1. Platform UI page: `/platform/operations/pilot-rollout`
   - Select tenant scope, window label (`baseline`/`pilot`/`custom`), and window days.
   - Click **Capture KPI Window**.
2. CLI command:
   - `php artisan rollout:pilot:capture-kpis --label=baseline --window-days=14`
   - Optional: `--company={TENANT_ID} --start="YYYY-MM-DD" --end="YYYY-MM-DD" --notes="..."`

Recorded data:
1. Table: `tenant_pilot_kpi_captures`
2. Audit action per capture: `tenant.rollout.pilot_kpi_capture.recorded`
