# Flowdesk Production Runbook

## Goal
Provide one operator-facing guide for deployment, recovery, and day-one support.

## Before Deploy
- Confirm `APP_ENV=production`
- Confirm `APP_DEBUG=false`
- Confirm HTTPS `APP_URL`
- Confirm real mail transport, queue backend, and cache backend
- Confirm provider secrets for SMS and payment rails
- Run `php artisan flowdesk:production:validate`
- Run `php artisan test`

## Deploy Steps
1. Put the platform in maintenance mode if required by your release window.
2. Pull the approved release.
3. Run `composer install --no-dev --optimize-autoloader`.
4. Run `php artisan migrate --force`.
5. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
6. Restart queue workers.
7. Confirm scheduler heartbeat updates.
8. Run tenant smoke tests.

## Queue And Scheduler Expectations
- Scheduler must run every minute.
- Queue workers must be supervised and auto-restarted.
- `failed_jobs` should be monitored continuously.
- The platform operations hub should show a recent scheduler heartbeat.

## Supervisor Example
Use one worker program and one scheduler program on a single VPS.

```ini
[program:flowdesk-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/flowdesk/artisan queue:work database --sleep=3 --tries=3 --timeout=120 --queue=default
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/flowdesk/storage/logs/supervisor-queue.log
stopwaitsecs=3600
```

```ini
[program:flowdesk-scheduler]
command=/usr/bin/php /var/www/flowdesk/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/flowdesk/storage/logs/supervisor-scheduler.log
stopwaitsecs=3600
```

Notes:
- Replace `/var/www/flowdesk` with your real deployment path.
- Keep only one `schedule:work` process running for the app.
- Restart both programs after each deploy so new code and config are loaded.

## Backup And Restore
- Take scheduled database backups at least daily.
- Keep storage backups for uploaded evidence and exported files.
- Test restore into a non-production environment before go-live and after major schema changes.
- Record the restore timestamp, restored tenant count, and validation results.

## Incident Triage
1. Capture the correlation ID from the request or log stream.
2. Check platform operations hub for scheduler delay, failed jobs, and blocking validation issues.
3. Inspect execution operations and tenant audit events using the same correlation ID.
4. Decide whether to retry, recover, reconcile manually, or roll back.
5. Record the incident outcome and follow-up owner.

## Rollback
- Roll back only if smoke checks fail or blocking tenant-facing regressions are detected.
- Restore database only when forward-fix is not safe.
- Re-run production validation and smoke tests after rollback.
