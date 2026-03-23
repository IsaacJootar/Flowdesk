<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RequestApprovalSlaProcessor;
use App\Services\TenantBillingAutomationService;
use App\Services\Execution\SubscriptionAutoBillingOrchestrator;
use App\Services\Execution\SubscriptionBillingAttemptProcessor;
use App\Services\Execution\ExecutionOpsAlertService;
use App\Services\Execution\ExecutionOpsAutoRecoveryService;
use App\Services\Operations\ProductionReadinessValidator;
use App\Services\Operations\RuntimeOperationsHealthService;
use App\Services\Procurement\LegacyVendorLinkBackfillService;
use App\Services\RequestCommunicationRetryService;
use App\Services\AssetCommunicationRetryService;
use App\Services\AssetReminderService;
use App\Services\VendorCommunicationRetryService;
use App\Services\VendorReminderService;
use App\Services\Rollout\CapturePilotKpiSnapshotService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('requests:process-sla {--company=} {--dry-run}', function (RequestApprovalSlaProcessor $processor): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $dryRun = (bool) $this->option('dry-run');

    $stats = $processor->process($companyId, $dryRun);

    $this->info('Request SLA processor completed.');
    $this->line('Pending scanned: '.$stats['pending_scanned']);
    $this->line('Due dates initialized: '.$stats['initialized_due_at']);
    $this->line('Reminder notifications sent: '.$stats['reminders_sent']);
    $this->line('Escalations sent: '.$stats['escalations_sent']);

    return self::SUCCESS;
})->purpose('Process request approval reminders and escalations');

Schedule::command('requests:process-sla')->everyTenMinutes()->withoutOverlapping();

Artisan::command('tenants:billing:automate', function (TenantBillingAutomationService $service): int {
    $updated = $service->evaluateAllExternal();

    $this->info('Tenant billing automation completed.');
    $this->line('Updated subscriptions: '.$updated);

    return self::SUCCESS;
})->purpose('Evaluate tenant billing state transitions from coverage + grace policy');

Schedule::command('tenants:billing:automate')
    ->hourly()
    ->withoutOverlapping();
Artisan::command('tenants:billing:auto-charge {--company=} {--no-queue}', function (SubscriptionAutoBillingOrchestrator $orchestrator): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $queueJobs = ! (bool) $this->option('no-queue');

    $stats = $orchestrator->dispatchDueBilling(
        companyId: $companyId,
        actor: null,
        queueJobs: $queueJobs
    );

    $this->info('Subscription auto-billing orchestration completed.');
    $this->line('Scanned: '.$stats['scanned']);
    $this->line('Queued: '.$stats['queued']);
    $this->line('Already exists: '.$stats['already_exists']);
    $this->line('Skipped (provider): '.$stats['skipped_provider']);
    $this->line('Skipped (zero amount): '.$stats['skipped_zero_amount']);

    return self::SUCCESS;
})->purpose('Queue subscription billing attempts for execution-enabled tenants');

Artisan::command('tenants:billing:process-queued {--company=} {--batch=200}', function (SubscriptionBillingAttemptProcessor $processor): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 200;

    $stats = $processor->processQueued(
        companyId: $companyId,
        batchSize: max(1, $batch)
    );

    $this->info('Queued billing attempts processor completed.');
    $this->line('Processed: '.$stats['processed']);

    return self::SUCCESS;
})->purpose('Process queued tenant subscription billing attempts');

Schedule::command('tenants:billing:auto-charge')
    ->dailyAt('02:10')
    ->withoutOverlapping();

Schedule::command('tenants:billing:process-queued --batch=500')
    ->everyTenMinutes()
    ->withoutOverlapping();

Artisan::command('execution:ops:alert-summary {--window=}', function (ExecutionOpsAlertService $service): int {
    $window = $this->option('window');
    $window = is_numeric($window) ? (int) $window : null;

    $summary = $service->emitWarnings($window);

    $this->info('Execution operations alert summary completed.');
    $this->line('Window (minutes): '. $summary['window_minutes']);
    $this->line('Failure threshold: '. $summary['threshold']);
    $this->line('Alerts emitted: '. count($summary['alerts']));

    return self::SUCCESS;
})->purpose('Emit warning logs for repeated execution failures by provider and tenant');

Schedule::command('execution:ops:alert-summary')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Artisan::command('execution:ops:auto-recover {--company=} {--older-than=} {--batch=} {--dry-run}', function (ExecutionOpsAutoRecoveryService $service): int {
    // Options are intentionally explicit for incident debugging and safe re-runs from shell.
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;

    $olderThan = $this->option('older-than');
    $olderThan = is_numeric($olderThan) ? (int) $olderThan : null;

    $batch = $this->option('batch');
    $batch = is_numeric($batch) ? (int) $batch : null;

    $dryRun = (bool) $this->option('dry-run');

    $summary = $service->run(
        companyId: $companyId,
        olderThanMinutes: $olderThan,
        maxPerPipeline: $batch,
        dryRun: $dryRun,
    );

    $this->info('Execution operations auto recovery completed.');
    $this->line('Enabled: '.($summary['enabled'] ? 'yes' : 'no'));
    $this->line('Dry-run: '.($summary['dry_run'] ? 'yes' : 'no'));
    $this->line('Older than (minutes): '.$summary['older_than_minutes']);
    $this->line('Max per pipeline: '.$summary['max_per_pipeline']);
    $this->line('Cooldown (minutes): '.$summary['cooldown_minutes']);
    $this->line('Totals - matched: '.$summary['totals']['matched'].', processed: '.$summary['totals']['processed'].', skipped: '.$summary['totals']['skipped'].', rejected: '.$summary['totals']['rejected']);

    foreach (['billing', 'payout', 'webhook'] as $pipeline) {
        $stats = $summary['results'][$pipeline];
        $this->line(ucfirst($pipeline).' - matched: '.$stats['matched'].', processed: '.$stats['processed'].', skipped: '.$stats['skipped'].', rejected: '.$stats['rejected']);
    }

    return self::SUCCESS;
})->purpose('Safely auto-recover queued execution records older than configured threshold');

// Scheduler uses a stricter batch cap than config default to keep background recovery low-risk.
Schedule::command('execution:ops:auto-recover --batch=100')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Artisan::command('procurement:backfill-vendor-links {--company=} {--dry-run} {--date-window=} {--amount-tolerance=} {--batch=200} {--skip-match} {--skip-payment-sync}', function (LegacyVendorLinkBackfillService $service): int {
    // Backfill runs are intentionally explicit so finance can preview impact before writing changes.
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;

    $batch = $this->option('batch');
    $batch = is_numeric($batch) ? (int) $batch : (int) config('procurement.backfill.batch_size', 200);

    $dateWindow = $this->option('date-window');
    $dateWindow = is_numeric($dateWindow) ? (int) $dateWindow : (int) config('procurement.backfill.invoice_date_window_days', 60);

    $amountTolerance = $this->option('amount-tolerance');
    $amountTolerance = is_numeric($amountTolerance) ? (float) $amountTolerance : (float) config('procurement.backfill.amount_tolerance_percent', 5);

    $dryRun = (bool) $this->option('dry-run');
    $recomputeMatch = ! (bool) $this->option('skip-match');
    $syncPayments = ! (bool) $this->option('skip-payment-sync');

    $summary = $service->run(
        companyId: $companyId,
        dryRun: $dryRun,
        batchSize: max(1, $batch),
        dateWindowDays: max(0, $dateWindow),
        amountTolerancePercent: max(0, $amountTolerance),
        recomputeMatch: $recomputeMatch,
        syncPayments: $syncPayments,
    );

    $this->info('Procurement legacy vendor backfill completed.');
    $this->line('Dry-run: '.($summary['dry_run'] ? 'yes' : 'no'));
    $this->line('Company scope: '.($summary['company_scope'] ?: 'all'));
    $this->line('Companies scanned: '.$summary['companies_scanned']);
    $this->line('Invoice summary - already linked: '.$summary['invoices']['already_linked'].', scanned: '.$summary['invoices']['scanned'].', eligible: '.$summary['invoices']['eligible'].', linked: '.$summary['invoices']['linked'].', ambiguous: '.$summary['invoices']['ambiguous'].', no candidate: '.$summary['invoices']['no_candidate'].', match passed: '.$summary['invoices']['match_passed'].', match failed: '.$summary['invoices']['match_failed'].', match skipped (no actor): '.$summary['invoices']['match_skipped_no_actor'].', errors: '.$summary['invoices']['errors']);
    $this->line('Payment summary - scanned: '.$summary['payments']['scanned'].', mismatch found: '.$summary['payments']['mismatch_found'].', synced: '.$summary['payments']['synced'].', errors: '.$summary['payments']['errors']);

    return self::SUCCESS;
})->purpose('Backfill legacy vendor invoice/payment links into procurement controls safely');
Artisan::command('rollout:pilot:capture-kpis {--company=} {--label=pilot} {--start=} {--end=} {--window-days=14} {--notes=}', function (CapturePilotKpiSnapshotService $service): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;

    $windowLabel = (string) ($this->option('label') ?? 'pilot');
    $windowDays = is_numeric($this->option('window-days')) ? max(1, (int) $this->option('window-days')) : 14;

    $start = trim((string) ($this->option('start') ?? ''));
    $end = trim((string) ($this->option('end') ?? ''));

    try {
        $windowEnd = $end !== '' ? \Illuminate\Support\Carbon::parse($end) : now();
        $windowStart = $start !== '' ? \Illuminate\Support\Carbon::parse($start) : $windowEnd->copy()->subDays($windowDays - 1)->startOfDay();
    } catch (\Throwable) {
        $this->error('Invalid --start or --end datetime format.');

        return self::FAILURE;
    }

    try {
        $summary = $service->captureWindow(
            companyId: $companyId,
            windowLabel: $windowLabel,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
            actor: null,
            notes: trim((string) ($this->option('notes') ?? '')) ?: null,
        );
    } catch (\Throwable $exception) {
        $this->error('Pilot KPI capture failed: '.$exception->getMessage());

        return self::FAILURE;
    }

    $this->info('Pilot rollout KPI capture completed.');
    $this->line('Window label: '.$summary['window_label']);
    $this->line('Window start: '.$summary['window_start']);
    $this->line('Window end: '.$summary['window_end']);
    $this->line('Tenant scope: '.($companyId ?: 'all eligible'));
    $this->line('Captured rows: '.$summary['captured']);

    return self::SUCCESS;
})->purpose('Capture baseline/pilot KPI snapshots for procurement + treasury rollout windows');

Artisan::command('requests:communications:retry-failed {--company=} {--batch=200}', function (RequestCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_retry_failed_batch', 200));
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->retryFailed(
        companyId: $companyId,
        batchSize: min($maxBatch, max(1, $batch))
    );

    $this->info('Retry failed communications completed.');
    $this->line('Retried: '.$stats['retried']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);

    return self::SUCCESS;
})->purpose('Retry failed request communication deliveries');

Artisan::command('requests:communications:process-queued {--company=} {--older-than=2} {--batch=500}', function (RequestCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_process_queued_batch', 500));
    $maxOlderThan = max(0, (int) config('communications.recovery.max_older_than_minutes', 10080));
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->processStuckQueued(
        companyId: $companyId,
        olderThanMinutes: min($maxOlderThan, max(0, $olderThan)),
        batchSize: min($maxBatch, max(1, $batch))
    );

    $this->info('Process queued communications completed.');
    $this->line('Processed: '.$stats['processed']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);
    $this->line('Remaining queued: '.$stats['remaining_queued']);

    return self::SUCCESS;
})->purpose('Process stuck queued request communication deliveries');

Schedule::command('requests:communications:process-queued --older-than=2 --batch=500')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('vendors:reminders:dispatch {--company=} {--vendor=} {--days-ahead=0}', function (VendorReminderService $service): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $vendorId = $this->option('vendor');
    $vendorId = is_numeric($vendorId) ? (int) $vendorId : null;
    $daysAhead = is_numeric($this->option('days-ahead')) ? (int) $this->option('days-ahead') : 0;

    $stats = $service->dispatchDueInvoiceReminders($companyId, $vendorId, max(0, $daysAhead));

    $this->info('Vendor reminders dispatch completed.');
    $this->line('Scanned: '.$stats['scanned']);
    $this->line('Queued: '.$stats['queued']);
    $this->line('Duplicates: '.$stats['duplicates']);
    $this->line('Missing recipient: '.$stats['missing_recipient']);
    $this->line('No channels: '.$stats['no_channels']);

    return self::SUCCESS;
})->purpose('Queue vendor invoice due/overdue reminders');

Schedule::command('vendors:reminders:dispatch --days-ahead=0')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('vendors:communications:retry-failed {--company=} {--vendor=} {--batch=200}', function (VendorCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $vendorId = $this->option('vendor');
    $vendorId = is_numeric($vendorId) ? (int) $vendorId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_retry_failed_batch', 200));
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->retryFailed($companyId, $vendorId, min($maxBatch, max(1, $batch)));

    $this->info('Retry failed vendor communications completed.');
    $this->line('Retried: '.$stats['retried']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);

    return self::SUCCESS;
})->purpose('Retry failed vendor communication deliveries');

Artisan::command('vendors:communications:process-queued {--company=} {--vendor=} {--older-than=2} {--batch=500}', function (VendorCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $vendorId = $this->option('vendor');
    $vendorId = is_numeric($vendorId) ? (int) $vendorId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_process_queued_batch', 500));
    $maxOlderThan = max(0, (int) config('communications.recovery.max_older_than_minutes', 10080));
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->processStuckQueued(
        $companyId,
        $vendorId,
        min($maxOlderThan, max(0, $olderThan)),
        min($maxBatch, max(1, $batch))
    );

    $this->info('Process queued vendor communications completed.');
    $this->line('Processed: '.$stats['processed']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);
    $this->line('Remaining queued: '.$stats['remaining_queued']);

    return self::SUCCESS;
})->purpose('Process stuck queued vendor communication deliveries');

Schedule::command('vendors:communications:process-queued --older-than=2 --batch=500')
    ->everyTenMinutes()
    ->withoutOverlapping();

Artisan::command('assets:reminders:dispatch {--company=} {--days-ahead=7}', function (AssetReminderService $service): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $daysAhead = is_numeric($this->option('days-ahead')) ? (int) $this->option('days-ahead') : 7;

    $stats = $service->dispatchDueReminders($companyId, max(0, $daysAhead));

    $this->info('Asset reminders dispatch completed.');
    $this->line('Scanned: '.$stats['scanned']);
    $this->line('Queued: '.$stats['queued']);
    $this->line('Duplicates: '.$stats['duplicates']);
    $this->line('Missing recipient: '.$stats['missing_recipient']);
    $this->line('No channels: '.$stats['no_channels']);

    return self::SUCCESS;
})->purpose('Queue asset maintenance/warranty reminders');

Schedule::command('assets:reminders:dispatch --days-ahead=7')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('assets:communications:retry-failed {--company=} {--batch=200}', function (AssetCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_retry_failed_batch', 200));
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->retryFailed($companyId, min($maxBatch, max(1, $batch)));

    $this->info('Retry failed asset communications completed.');
    $this->line('Retried: '.$stats['retried']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);

    return self::SUCCESS;
})->purpose('Retry failed asset reminder communication deliveries');

Artisan::command('assets:communications:process-queued {--company=} {--older-than=2} {--batch=500}', function (AssetCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
    $defaultBatch = max(1, (int) config('communications.recovery.default_process_queued_batch', 500));
    $maxOlderThan = max(0, (int) config('communications.recovery.max_older_than_minutes', 10080));
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : $defaultBatch;

    $stats = $retryService->processStuckQueued(
        $companyId,
        min($maxOlderThan, max(0, $olderThan)),
        min($maxBatch, max(1, $batch))
    );

    $this->info('Process queued asset communications completed.');
    $this->line('Processed: '.$stats['processed']);
    $this->line('Sent: '.$stats['sent']);
    $this->line('Failed: '.$stats['failed']);
    $this->line('Skipped: '.$stats['skipped']);
    $this->line('Remaining queued: '.$stats['remaining_queued']);

    return self::SUCCESS;
})->purpose('Process stuck queued asset reminder communication deliveries');

Schedule::command('assets:communications:process-queued --older-than=2 --batch=500')
    ->everyTenMinutes()
    ->withoutOverlapping();

Artisan::command('flowdesk:ops:heartbeat', function (RuntimeOperationsHealthService $service): int {
    // A small heartbeat gives platform ops a cheap signal that the scheduler is
    // still alive even when no business-facing jobs fire in a given minute.
    $service->recordSchedulerHeartbeat();

    $this->info('Flowdesk scheduler heartbeat recorded.');

    return self::SUCCESS;
})->purpose('Record the scheduler heartbeat for runtime health monitoring');

Schedule::command('flowdesk:ops:heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('flowdesk:production:validate', function (ProductionReadinessValidator $validator): int {
    $summary = $validator->summary();

    $this->info('Flowdesk production validation completed.');
    $this->line('Blocking issues: '.(int) $summary['blocking']);
    $this->line('Warnings: '.(int) $summary['warning']);

    foreach ((array) ($summary['issues'] ?? []) as $issue) {
        $this->line(sprintf('[%s] %s - %s', strtoupper((string) ($issue['severity'] ?? 'warning')), (string) ($issue['code'] ?? 'issue'), (string) ($issue['message'] ?? 'Validation issue detected.')));
    }

    return (int) ($summary['blocking'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Validate Flowdesk production readiness guardrails');
