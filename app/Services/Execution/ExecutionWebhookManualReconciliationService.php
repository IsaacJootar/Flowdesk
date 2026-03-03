<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Models\User;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\SyncWebhookExecutionExceptionService;

class ExecutionWebhookManualReconciliationService
{
    public function __construct(
        private readonly SubscriptionBillingAttemptProcessor $billingAttemptProcessor,
        private readonly RequestPayoutExecutionAttemptProcessor $payoutAttemptProcessor,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly SyncWebhookExecutionExceptionService $syncWebhookExecutionExceptionService,
    ) {
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function reconcile(ExecutionWebhookEvent $event, string $reason, ?User $actor): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return [
                'ok' => false,
                'message' => 'Operator reason is required for manual reconciliation.',
            ];
        }

        if ((string) $event->verification_status !== 'valid') {
            return [
                'ok' => false,
                'message' => 'Only valid webhook events can be reconciled manually.',
            ];
        }

        $nextStatus = $this->statusFromEventType((string) ($event->event_type ?? ''));
        if ($nextStatus === null) {
            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'Manual reconcile skipped: event type is not mapped. Reason: '.$reason,
                'processed_at' => now(),
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'manual_reconcile');

            $this->logAudit($event, $actor, 'tenant.execution.webhook.manual_ignored', [
                'reason' => $reason,
                'event_type' => (string) ($event->event_type ?? ''),
            ]);

            return [
                'ok' => true,
                'message' => 'Event is not mapped to a lifecycle status. Marked as ignored.',
            ];
        }

        $payoutAttempt = $event->payoutAttempt;
        if ($payoutAttempt instanceof RequestPayoutExecutionAttempt) {
            $this->payoutAttemptProcessor->markFromWebhook(
                attempt: $payoutAttempt,
                nextStatus: $nextStatus,
                eventId: (string) ($event->external_event_id ?? ''),
                normalizedPayload: (array) ($event->normalized_payload ?? []),
            );

            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'failure_reason' => 'Manual reconcile completed. Reason: '.$reason,
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'manual_reconcile');

            $this->logAudit($event, $actor, 'tenant.execution.webhook.manual_reconciled_payout', [
                'reason' => $reason,
                'next_status' => $nextStatus,
                'payout_attempt_id' => (int) $payoutAttempt->id,
            ]);

            return [
                'ok' => true,
                'message' => 'Webhook reconciled to payout execution lifecycle.',
            ];
        }

        $billingAttempt = $event->billingAttempt;
        if ($billingAttempt instanceof TenantSubscriptionBillingAttempt) {
            $this->billingAttemptProcessor->markFromWebhook(
                attempt: $billingAttempt,
                nextStatus: $nextStatus,
                eventId: (string) ($event->external_event_id ?? ''),
                normalizedPayload: (array) ($event->normalized_payload ?? []),
            );

            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'failure_reason' => 'Manual reconcile completed. Reason: '.$reason,
            ])->save();
            $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'manual_reconcile');

            $this->logAudit($event, $actor, 'tenant.execution.webhook.manual_reconciled_billing', [
                'reason' => $reason,
                'next_status' => $nextStatus,
                'billing_attempt_id' => (int) $billingAttempt->id,
            ]);

            return [
                'ok' => true,
                'message' => 'Webhook reconciled to billing lifecycle.',
            ];
        }

        $event->forceFill([
            'processing_status' => 'failed',
            'failure_reason' => 'Manual reconcile failed: no linked billing/payout attempt. Reason: '.$reason,
            'processed_at' => now(),
        ])->save();
        $this->syncWebhookExecutionExceptionService->syncForEvent($event, 'manual_reconcile');

        $this->logAudit($event, $actor, 'tenant.execution.webhook.manual_failed', [
            'reason' => $reason,
        ]);

        return [
            'ok' => false,
            'message' => 'No linked billing/payout attempt was found for this webhook event.',
        ];
    }

    private function statusFromEventType(string $eventType): ?string
    {
        $normalized = strtolower(trim($eventType));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'settled') || str_contains($normalized, 'succeeded') || str_contains($normalized, 'paid')) {
            return 'settled';
        }

        if (str_contains($normalized, 'failed') || str_contains($normalized, 'declined')) {
            return 'failed';
        }

        if (str_contains($normalized, 'reversed') || str_contains($normalized, 'refund') || str_contains($normalized, 'chargeback')) {
            return 'reversed';
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private function logAudit(ExecutionWebhookEvent $event, ?User $actor, string $action, array $metadata = []): void
    {
        $companyId = (int) ($event->company_id ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $this->tenantAuditLogger->log(
            companyId: $companyId,
            action: $action,
            actor: $actor,
            description: 'Manual webhook reconciliation action from platform operations center.',
            entityType: ExecutionWebhookEvent::class,
            entityId: (int) $event->id,
            metadata: array_merge([
                'provider' => (string) ($event->provider_key ?? ''),
                'external_event_id' => (string) ($event->external_event_id ?? ''),
            ], $metadata),
        );
    }
}
