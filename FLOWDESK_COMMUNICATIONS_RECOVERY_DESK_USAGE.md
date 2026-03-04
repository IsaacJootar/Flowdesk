# Flowdesk Communications Recovery Desk Usage

Last updated: 2026-03-04

## Purpose
The Communications Recovery Desk gives support/ops one place to recover failed or stuck queued communication logs across:
- Requests
- Vendors
- Assets

Route:
- `/requests/communications` (Recovery Desk tab)

Help page:
- `/requests/communications/help`

## Who can access
- View desk: `owner`, `finance`, `manager`, `auditor`
- Execute retry/process actions: `owner`, `finance`

## Recovery workflow
1. Open `Inbox & Logs` and switch to `Recovery Desk`.
2. Set `Display Scope` (`All modules`, `Requests`, `Vendors`, `Assets`).
3. Set `Display Age Filter (mins)` for queued backlog visibility.
4. Review cards:
   - Active Recovery Items
   - Failed
   - Stuck Queued
5. Review breakdowns:
   - Failed/Queued by Module
   - Channel Issues
   - Recipient / Config Breakdown
6. Run action:
   - `Retry Failed` for failed rows in current scope
   - `Process Queued` for queued rows older than the age filter
7. Use `Retry now` on a single row when only one delivery needs intervention.

## What breakdowns mean
- Missing recipient email/phone: recipient contact details are incomplete.
- Channel disabled or unconfigured: tenant communication policy/config blocks delivery.
- Unsupported channel: event channel mapping is invalid.
- Provider/send error: transport failed while sending.
- No recipient target: no user/email/phone target was available.

## Operational notes
- Recovery actions are tenant-scoped and never cross organization boundaries.
- The desk uses queued-age filtering for actionable backlog, so very recent queued rows are excluded until they pass the selected age threshold.
- Use Incident History and Audit Logs for repeated failures or policy-related incidents.
