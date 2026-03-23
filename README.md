# Flowdesk

Flowdesk is a multi-tenant finance operations platform built on Laravel and Livewire. The app combines request approvals, procurement controls, vendors, treasury reconciliation, execution operations, assets, reports, and platform rollout tooling in one codebase.

## What Is In The Project

- Tenant-facing workspaces for requests, vendors, procurement, treasury, execution, assets, budgets, and reporting
- Platform-facing operations pages for rollout, execution health, incident history, and tenant management
- Queue and scheduler driven automation for billing, payout recovery, reminders, retries, and KPI capture
- Internal operations documentation for rollout, provider integration, and production hardening

## Local Setup

1. Copy `.env.example` to `.env`.
2. Configure database, mail, queue, cache, and provider credentials.
3. Install backend dependencies with `composer install`.
4. Install frontend dependencies with `npm install`.
5. Generate an app key with `php artisan key:generate`.
6. Run migrations with `php artisan migrate`.
7. Build frontend assets with `npm run build` for production or `npm run dev` locally.

## Production Baseline

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Use HTTPS and a production `APP_URL`
- Use a real mail transport instead of `log`
- Prefer Redis for cache and queue in production
- Run the scheduler continuously with `php artisan schedule:work` or cron for `php artisan schedule:run`
- Run queue workers under a supervisor instead of ad-hoc shells
- Monitor failed jobs, storage growth, and webhook delivery health

## Important Runtime Commands

- `php artisan test`
- `php artisan flowdesk:production:validate`
- `php artisan flowdesk:ops:heartbeat`
- `php artisan requests:process-sla`
- `php artisan tenants:billing:auto-charge`
- `php artisan tenants:billing:process-queued --batch=500`
- `php artisan execution:ops:alert-summary`
- `php artisan execution:ops:auto-recover --batch=100`
- `php artisan vendors:communications:process-queued --older-than=2 --batch=500`
- `php artisan assets:communications:process-queued --older-than=2 --batch=500`

## Key Docs

- `FLOWDESK_NEEDED_IMPROVEMENTS.md`
- `FLOWDESK_FINAL_PRODUCTION_APPROVAL_ITEMS.md`
- `FLOWDESK_FINAL_EXECUTION_CHECKLIST.md`
- `FLOWDESK_PRODUCTION_RUNBOOK.md`
- `FLOWDESK_SCALE_VALIDATION_RUNBOOK.md`
- `FLOWDESK_UAT_SIGNOFF_PACK.md`
- `FLOWDESK_MODULE_STATUS.md`
- `FLOWDESK_REAL_PROVIDER_INTEGRATION_GUIDE.md`
- `FLOWDESK_TWO_MODE_TENANT_EXECUTION_PLAN.md`
- `FLOWDESK_ROL803_BACKFILL_SOP_RUNBOOK.md`
- `FLOWDESK_ROL805_PILOT_CHECKLIST_AND_KPI_TEMPLATE.md`
- `FLOWDESK_DATABASE.md`

## Current Architecture Notes

- Tenant routes are separated from platform routes.
- Tenant-scoped models use company-aware scoping and can now also run inside an explicit tenant context for non-HTTP flows.
- Request lifecycle and vendor command center data are now built through dedicated services instead of large Livewire page classes.
- Treasury auto-reconciliation prefetches chunk-sized candidate pools to avoid repeatedly scanning full tenant history.

## Deployment Checklist Before Go-Live

- Confirm production env values and provider secrets
- Run `php artisan flowdesk:production:validate`
- Run migrations on production
- Warm config and route caches
- Confirm queue workers and scheduler are healthy
- Verify backup, restore, and error-monitoring paths
- Run smoke tests for tenant login, request approval, payout queue, vendor invoice flow, and treasury reconciliation
