<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RequestApprovalSlaProcessor;
use App\Services\TenantBillingAutomationService;
use App\Services\RequestCommunicationRetryService;
use App\Services\AssetCommunicationRetryService;
use App\Services\AssetReminderService;
use App\Services\VendorCommunicationRetryService;
use App\Services\VendorReminderService;

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

Artisan::command('requests:communications:retry-failed {--company=} {--batch=200}', function (RequestCommunicationRetryService $retryService): int {
    $companyId = $this->option('company');
    $companyId = is_numeric($companyId) ? (int) $companyId : null;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 200;

    $stats = $retryService->retryFailed(
        companyId: $companyId,
        batchSize: max(1, $batch)
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
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 500;

    $stats = $retryService->processStuckQueued(
        companyId: $companyId,
        olderThanMinutes: max(0, $olderThan),
        batchSize: max(1, $batch)
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
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 200;

    $stats = $retryService->retryFailed($companyId, $vendorId, max(1, $batch));

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
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 500;

    $stats = $retryService->processStuckQueued($companyId, $vendorId, max(0, $olderThan), max(1, $batch));

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
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 200;

    $stats = $retryService->retryFailed($companyId, max(1, $batch));

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
    $olderThan = is_numeric($this->option('older-than')) ? (int) $this->option('older-than') : 2;
    $batch = is_numeric($this->option('batch')) ? (int) $this->option('batch') : 500;

    $stats = $retryService->processStuckQueued($companyId, max(0, $olderThan), max(1, $batch));

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
