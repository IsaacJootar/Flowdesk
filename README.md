# Flowdesk

Flowdesk is a multi-tenant finance operations platform built on Laravel and Livewire. It brings requests, approvals, procurement, vendors, treasury reconciliation, execution operations, assets, and reporting into one workspace with both tenant and platform views.

## Highlights

- Requests lifecycle with approvals, payouts, and audit trail
- Procurement + treasury control lanes with match and reconciliation workflows
- Vendor payables, assets, and budget oversight
- Operations hub for execution health, incidents, and rollout KPIs
- Multi-tenant architecture with role-based controls

## Tech Stack

- Laravel + Livewire
- MySQL/MariaDB (primary)
- Vite + Tailwind

## Local Setup

1. Copy `.env.example` to `.env`.
2. Configure database, mail, queue, cache, and provider credentials.
3. Install backend dependencies with `composer install`.
4. Install frontend dependencies with `npm install`.
5. Generate an app key with `php artisan key:generate`.
6. Run migrations with `php artisan migrate`.
7. Build frontend assets with `npm run build` or `npm run dev`.

## MailerSend Setup (Production)

MailerSend is the default and supported transactional mail service.

Required `.env` values:

- `MAIL_MAILER=mailersend_failover`
- `MAIL_TRANSACTIONAL_MAILER=mailersend_failover`
- `MAIL_HOST=smtp.mailersend.net`
- `MAIL_PORT=587`
- `MAIL_USERNAME`, `MAIL_PASSWORD`
- `MAIL_ENCRYPTION=tls`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `MAILERSEND_API_KEY`
- `MAILERSEND_DOMAIN`
- `MAILERSEND_WEBHOOK_SECRET`

Webhook endpoint (for delivery events):

- `POST https://your-domain.com/webhooks/mailersend`

## Queues and Scheduler

For production stability:

- Run the scheduler continuously (`php artisan schedule:work` or cron).
- Run queue workers under a supervisor.
- Monitor failed jobs and mail delivery events.

## Useful Commands

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

## Deployment Checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure HTTPS and `APP_URL`.
- Set MailerSend credentials and verified `MAIL_FROM_ADDRESS`.
- Run migrations on production.
- Cache config/routes (`php artisan config:cache` / `php artisan route:cache`).
- Verify queues + scheduler are healthy.
- Run smoke tests for login, requests, approvals, and treasury reconciliation.
