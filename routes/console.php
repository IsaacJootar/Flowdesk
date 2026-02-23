<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\RequestApprovalSlaProcessor;

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
