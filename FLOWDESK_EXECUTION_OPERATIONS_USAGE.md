# Flowdesk Execution Operations Usage Guide

Last updated: 2026-03-16

This guide defines the tenant payout execution workflow end-to-end so money movement operations are clear for all staff roles.

## 1) Tenant Execution Pages

- Execution Health: `/execution/health`
- Payout Ready Queue: `/execution/payout-ready`
- Help / Usage Guide: `/execution/help`

How to use:
- Work page: `Payout Ready Queue`
- Incident posture page: `Execution Health`
- Reference page: `Help / Usage Guide`

## 2) End-to-End Money Movement Workflow

### Step 1: Approval chain completes
- Request is approved by all required steps.
- In execution-enabled tenants, final approval transitions toward payout execution.

### Step 2: Request becomes payout-ready
- Request status is set to `approved_for_execution`.
- This means: approval is complete, waiting for payout queueing/dispatch.

### Step 3: Queueing checks run
Queueing is attempted by the orchestrator and only succeeds if:
- Tenant execution mode is `execution_enabled`
- Provider is configured
- Amount is valid (`> 0`)
- Procurement payment gate passes (if enabled)

If checks fail:
- Request can remain `approved_for_execution`
- Metadata/audit captures the block reason (for example procurement gate)

### Step 4: Payout attempt is created
- Request payout attempt row is created.
- Request status becomes `execution_queued`.

### Step 5: Processor starts execution
- Execution processor picks queued attempt.
- Request status becomes `execution_processing`.

### Step 6: Provider/Webhook outcome resolves
Possible outcomes include:
- `settled`
- `failed`
- `reversed`
- `webhook_pending`
- `skipped` (no-op/unconfigured provider path)

Request status is synced from payout outcome by processor logic.

### Step 7: Failed rows remain actionable
- Failed payout requests stay in queue view so finance can retry from one place.
- UI action label for this is `Rerun Payout`.

## 3) What the Queue Includes

`Requests Waiting for Payout` currently includes request statuses:
- `approved_for_execution`
- `execution_queued`
- `execution_processing`
- `failed`

Why `failed` is included:
- Operationally intentional, so retries happen in the same execution workspace.

## 4) Card Counters Meaning

- `Total Waiting`: all rows in the four statuses above
- `Ready`: `approved_for_execution`
- `Queued`: `execution_queued`
- `Processing`: `execution_processing`
- `Failed`: `failed`

Example:
- Total Waiting `3`, Ready `1`, Queued `1`, Processing `0`, Failed `1`
- Interpretation: one request ready to queue, one queued, one failed awaiting rerun.

## 5) Action Buttons in Queue

- `Run Payout`: first-time run for non-failed rows
- `Rerun Payout`: retry action for failed rows
- `Re-check`: row already in processing/webhook-pending flow

## 6) Status Triggers (Source of Truth)

### `approved_for_execution`
- Trigger: final required approval in execution-enabled flow.
- Code: `app/Actions/Requests/DecideSpendRequest.php`

### `execution_queued`
- Trigger: orchestrator successfully creates payout attempt.
- Code: `app/Services/Execution/RequestPayoutExecutionOrchestrator.php`

### `execution_processing`
- Trigger: attempt processor starts execution.
- Code: `app/Services/Execution/RequestPayoutExecutionAttemptProcessor.php`

### `failed`
- Trigger: payout execution resolves as failed.
- Code: `app/Services/Execution/RequestPayoutExecutionAttemptProcessor.php`

## 7) Queue Order Clarification

- Queue operations are time-based, but strict global FIFO is not guaranteed in all scenarios.
- Retries, manual reruns, and worker timing can affect observed order.
- UI sort is triage-oriented (for visibility), not pure FIFO order.

## 8) Final Approver Column (`-`)

`Final Approver` uses approval history (`request_approvals` with approved action and actor).

If it shows `-`, common reasons:
- Seeded/system data moved directly to execution statuses
- Historical approval actor linkage missing

This indicates missing approver trail for that row, not necessarily an invalid payout record.

## 9) Ops Troubleshooting Checklist

1. Confirm tenant execution mode and provider configuration.
2. Confirm procurement gate is not blocking queueing.
3. For failed rows, use `Rerun Payout` after checking condition message.
4. If still failing, review provider/config/state and incident context in `Execution Health`.
5. Escalate using incident ID when repeated failures persist.

## 10) Role Expectations (Tenant Side)

- Owner: oversight + execution unblock decisions
- Finance: primary payout queue operator
- Manager: operational support where allowed
- Auditor: view/traceability, typically no execution action

## 11) Full Manual Later

This guide is the execution chapter foundation for the final full Flowdesk usage manual (all modules + roles + runbooks).

## 12) Platform AI Runtime Health Workflow

Page:
- Platform route: `/platform/operations/ai-runtime-health`

Purpose:
- Show if model runtime is reachable (for example Ollama).
- Show whether OCR binaries are available (`tesseract`, `pdftotext`).
- Show 24h receipt-analysis mix (`model_assisted` vs `deterministic`) and fallback rate.
- Show the latest cross-tenant receipt-analysis events for quick triage.

How platform ops should use it:
1. Open `AI Runtime Health` from Operations Hub or Execution Operations.
2. Check runtime status cards first (provider, primary model, model reachability, OCR capability).
3. Review fallback rate and model-assisted volume over the last 24 hours.
4. Open recent analysis rows to confirm whether fallback is isolated or widespread.
5. If model runtime or OCR is unavailable, continue operations in deterministic mode and raise infra action item.

Important behavior:
- Receipt Agent remains operational even when model runtime is down; deterministic extraction is used as fallback.
- Monitor metrics are platform-level and intentionally bypass tenant query scope for reliability oversight.
