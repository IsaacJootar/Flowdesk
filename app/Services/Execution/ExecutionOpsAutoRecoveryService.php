<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;

class ExecutionOpsAutoRecoveryService
{
    /**
     * @var array<string,array{company_id:int,pipeline:string,provider_key:string,matched:int,processed:int,skipped:int,rejected:int}>
     */
    private array $runSummaries = [];

    public function __construct(
        private readonly SubscriptionBillingAttemptProcessor $billingProcessor,
        private readonly RequestPayoutExecutionAttemptProcessor $payoutProcessor,
        private readonly ExecutionWebhookManualReconciliationService $webhookManualReconciliationService,
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    /**
     * @return array{
     *     enabled:bool,
     *     dry_run:bool,
     *     older_than_minutes:int,
     *     max_per_pipeline:int,
     *     cooldown_minutes:int,
     *     results:array{billing:array{matched:int,processed:int,skipped:int,rejected:int},payout:array{matched:int,processed:int,skipped:int,rejected:int},webhook:array{matched:int,processed:int,skipped:int,rejected:int}},
     *     totals:array{matched:int,processed:int,skipped:int,rejected:int}
     * }
     */
    public function run(
        ?int $companyId = null,
        ?int $olderThanMinutes = null,
        ?int $maxPerPipeline = null,
        bool $dryRun = false,
    ): array {
        $enabled = (bool) config('execution.ops_recovery.enabled', true);
        $olderThanMinutes = max(1, (int) ($olderThanMinutes ?? config('execution.ops_recovery.older_than_minutes', 30)));
        $maxPerPipeline = max(1, (int) ($maxPerPipeline ?? config('execution.ops_recovery.max_per_pipeline', 200)));
        $cooldownMinutes = max(0, (int) config('execution.ops_recovery.cooldown_minutes', 15));

        $this->runSummaries = [];

        $summary = [
            'enabled' => $enabled,
            'dry_run' => $dryRun,
            'older_than_minutes' => $olderThanMinutes,
            'max_per_pipeline' => $maxPerPipeline,
            'cooldown_minutes' => $cooldownMinutes,
            'results' => [
                'billing' => ['matched' => 0, 'processed' => 0, 'skipped' => 0, 'rejected' => 0],
                'payout' => ['matched' => 0, 'processed' => 0, 'skipped' => 0, 'rejected' => 0],
                'webhook' => ['matched' => 0, 'processed' => 0, 'skipped' => 0, 'rejected' => 0],
            ],
            'totals' => ['matched' => 0, 'processed' => 0, 'skipped' => 0, 'rejected' => 0],
        ];

        if (! $enabled && ! $dryRun) {
            return $summary;
        }

        $cutoff = Carbon::now()->subMinutes($olderThanMinutes);
        $cooldownCutoff = Carbon::now()->subMinutes($cooldownMinutes);

        $summary['results']['billing'] = $this->recoverBilling(
            companyId: $companyId,
            cutoff: $cutoff,
            cooldownCutoff: $cooldownCutoff,
            cooldownMinutes: $cooldownMinutes,
            maxPerPipeline: $maxPerPipeline,
            olderThanMinutes: $olderThanMinutes,
            dryRun: $dryRun,
        );

        $summary['results']['payout'] = $this->recoverPayout(
            companyId: $companyId,
            cutoff: $cutoff,
            cooldownCutoff: $cooldownCutoff,
            cooldownMinutes: $cooldownMinutes,
            maxPerPipeline: $maxPerPipeline,
            olderThanMinutes: $olderThanMinutes,
            dryRun: $dryRun,
        );

        $summary['results']['webhook'] = $this->recoverWebhook(
            companyId: $companyId,
            cutoff: $cutoff,
            cooldownCutoff: $cooldownCutoff,
            cooldownMinutes: $cooldownMinutes,
            maxPerPipeline: $maxPerPipeline,
            olderThanMinutes: $olderThanMinutes,
            dryRun: $dryRun,
        );

        foreach ($summary['results'] as $pipelineStats) {
            $summary['totals']['matched'] += (int) $pipelineStats['matched'];
            $summary['totals']['processed'] += (int) $pipelineStats['processed'];
            $summary['totals']['skipped'] += (int) $pipelineStats['skipped'];
            $summary['totals']['rejected'] += (int) $pipelineStats['rejected'];
        }

        if (! $dryRun) {
            // Persist one summary row per tenant + pipeline + provider for UI reporting.
            $this->persistRunSummaries($olderThanMinutes, $maxPerPipeline, $cooldownMinutes);
        }

        return $summary;
    }

    /**
     * @return array{matched:int,processed:int,skipped:int,rejected:int}
     */
    private function recoverBilling(
        ?int $companyId,
        Carbon $cutoff,
        Carbon $cooldownCutoff,
        int $cooldownMinutes,
        int $maxPerPipeline,
        int $olderThanMinutes,
        bool $dryRun,
    ): array {
        $query = TenantSubscriptionBillingAttempt::query()
            ->where('attempt_status', 'queued')
            ->where('queued_at', '<=', $cutoff);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($cooldownMinutes > 0) {
            // Cooldown prevents immediate re-processing loops when records are just touched.
            $query->where('updated_at', '<=', $cooldownCutoff);
        }

        $attempts = $query->orderBy('id')->limit($maxPerPipeline)->get();

        $stats = [
            'matched' => $attempts->count(),
            'processed' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        foreach ($attempts as $attempt) {
            $providerKey = (string) ($attempt->provider_key ?? 'unknown');
            $this->trackRunSummary((int) $attempt->company_id, 'billing', $providerKey, 'matched');

            if ($dryRun) {
                continue;
            }

            if (! $this->billingProcessor->processAttemptById((int) $attempt->id)) {
                $stats['rejected']++;
                $this->trackRunSummary((int) $attempt->company_id, 'billing', $providerKey, 'rejected');

                continue;
            }

            $stats['processed']++;
            $this->trackRunSummary((int) $attempt->company_id, 'billing', $providerKey, 'processed');

            $latest = TenantSubscriptionBillingAttempt::query()
                ->select('attempt_status')
                ->find((int) $attempt->id);

            $finalStatus = (string) ($latest?->attempt_status ?? 'unknown');
            if ($finalStatus === 'skipped') {
                $stats['skipped']++;
                $this->trackRunSummary((int) $attempt->company_id, 'billing', $providerKey, 'skipped');
            }

            $this->tenantAuditLogger->log(
                companyId: (int) $attempt->company_id,
                action: 'tenant.execution.billing.auto_recovered_queued',
                actor: null,
                description: 'Queued billing attempt auto-recovered by execution guardrail scheduler.',
                entityType: TenantSubscriptionBillingAttempt::class,
                entityId: (int) $attempt->id,
                metadata: [
                    'reason' => 'Automated recovery guardrail run',
                    'older_than_minutes' => $olderThanMinutes,
                    'final_status' => $finalStatus,
                ],
            );
        }

        return $stats;
    }

    /**
     * @return array{matched:int,processed:int,skipped:int,rejected:int}
     */
    private function recoverPayout(
        ?int $companyId,
        Carbon $cutoff,
        Carbon $cooldownCutoff,
        int $cooldownMinutes,
        int $maxPerPipeline,
        int $olderThanMinutes,
        bool $dryRun,
    ): array {
        $query = RequestPayoutExecutionAttempt::query()
            ->where('execution_status', 'queued')
            ->where('queued_at', '<=', $cutoff);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($cooldownMinutes > 0) {
            $query->where('updated_at', '<=', $cooldownCutoff);
        }

        $attempts = $query->orderBy('id')->limit($maxPerPipeline)->get();

        $stats = [
            'matched' => $attempts->count(),
            'processed' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        foreach ($attempts as $attempt) {
            $providerKey = (string) ($attempt->provider_key ?? 'unknown');
            $this->trackRunSummary((int) $attempt->company_id, 'payout', $providerKey, 'matched');

            if ($dryRun) {
                continue;
            }

            if (! $this->payoutProcessor->processAttemptById((int) $attempt->id)) {
                $stats['rejected']++;
                $this->trackRunSummary((int) $attempt->company_id, 'payout', $providerKey, 'rejected');

                continue;
            }

            $stats['processed']++;
            $this->trackRunSummary((int) $attempt->company_id, 'payout', $providerKey, 'processed');

            $latest = RequestPayoutExecutionAttempt::query()
                ->select('execution_status')
                ->find((int) $attempt->id);

            $finalStatus = (string) ($latest?->execution_status ?? 'unknown');
            if ($finalStatus === 'skipped') {
                $stats['skipped']++;
                $this->trackRunSummary((int) $attempt->company_id, 'payout', $providerKey, 'skipped');
            }

            $this->tenantAuditLogger->log(
                companyId: (int) $attempt->company_id,
                action: 'tenant.execution.payout.auto_recovered_queued',
                actor: null,
                description: 'Queued payout attempt auto-recovered by execution guardrail scheduler.',
                entityType: RequestPayoutExecutionAttempt::class,
                entityId: (int) $attempt->id,
                metadata: [
                    'reason' => 'Automated recovery guardrail run',
                    'older_than_minutes' => $olderThanMinutes,
                    'final_status' => $finalStatus,
                ],
            );
        }

        return $stats;
    }

    /**
     * @return array{matched:int,processed:int,skipped:int,rejected:int}
     */
    private function recoverWebhook(
        ?int $companyId,
        Carbon $cutoff,
        Carbon $cooldownCutoff,
        int $cooldownMinutes,
        int $maxPerPipeline,
        int $olderThanMinutes,
        bool $dryRun,
    ): array {
        $query = ExecutionWebhookEvent::query()
            ->where('processing_status', 'queued')
            ->where('received_at', '<=', $cutoff);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($cooldownMinutes > 0) {
            $query->where('updated_at', '<=', $cooldownCutoff);
        }

        $events = $query->orderBy('id')->limit($maxPerPipeline)->get();

        $stats = [
            'matched' => $events->count(),
            'processed' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        foreach ($events as $event) {
            $companyId = (int) ($event->company_id ?? 0);
            $providerKey = (string) ($event->provider_key ?? 'unknown');
            $this->trackRunSummary($companyId, 'webhook', $providerKey, 'matched');

            if ($dryRun) {
                continue;
            }

            $result = $this->webhookManualReconciliationService->reconcile(
                event: $event,
                reason: 'Automated recovery guardrail run',
                actor: null,
            );

            if (! ($result['ok'] ?? false)) {
                $stats['rejected']++;
                $this->trackRunSummary($companyId, 'webhook', $providerKey, 'rejected');

                continue;
            }

            $stats['processed']++;
            $this->trackRunSummary($companyId, 'webhook', $providerKey, 'processed');

            $latest = ExecutionWebhookEvent::query()
                ->select('processing_status')
                ->find((int) $event->id);

            $this->tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.execution.webhook.auto_recovered_queued',
                actor: null,
                description: 'Queued webhook event auto-recovered by execution guardrail scheduler.',
                entityType: ExecutionWebhookEvent::class,
                entityId: (int) $event->id,
                metadata: [
                    'reason' => 'Automated recovery guardrail run',
                    'older_than_minutes' => $olderThanMinutes,
                    'final_status' => (string) ($latest?->processing_status ?? 'unknown'),
                ],
            );
        }

        return $stats;
    }

    private function trackRunSummary(int $companyId, string $pipeline, string $providerKey, string $metric): void
    {
        if ($companyId <= 0) {
            return;
        }

        $providerKey = trim($providerKey) !== '' ? trim($providerKey) : 'unknown';
        $key = $companyId.'|'.$pipeline.'|'.$providerKey;

        if (! isset($this->runSummaries[$key])) {
            $this->runSummaries[$key] = [
                'company_id' => $companyId,
                'pipeline' => $pipeline,
                'provider_key' => $providerKey,
                'matched' => 0,
                'processed' => 0,
                'skipped' => 0,
                'rejected' => 0,
            ];
        }

        if (isset($this->runSummaries[$key][$metric])) {
            $this->runSummaries[$key][$metric]++;
        }
    }

    private function persistRunSummaries(int $olderThanMinutes, int $maxPerPipeline, int $cooldownMinutes): void
    {
        foreach ($this->runSummaries as $row) {
            $this->tenantAuditLogger->log(
                companyId: (int) $row['company_id'],
                action: 'tenant.execution.auto_recovery.run_summary',
                actor: null,
                description: 'Automated execution recovery summary recorded for operations reporting.',
                entityType: null,
                entityId: null,
                metadata: [
                    'pipeline' => $row['pipeline'],
                    'provider_key' => $row['provider_key'],
                    'matched' => (int) $row['matched'],
                    'processed' => (int) $row['processed'],
                    'skipped' => (int) $row['skipped'],
                    'rejected' => (int) $row['rejected'],
                    'older_than_minutes' => $olderThanMinutes,
                    'max_per_pipeline' => $maxPerPipeline,
                    'cooldown_minutes' => $cooldownMinutes,
                    'trigger' => 'execution:ops:auto-recover',
                ],
            );
        }
    }
}
