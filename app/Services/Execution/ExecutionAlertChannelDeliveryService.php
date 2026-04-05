<?php

namespace App\Services\Execution;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Enums\UserRole;
use App\Mail\ExecutionAlertMail;
use App\Models\User;
use App\Services\TransactionalEmailSender;
use App\Services\TenantAuditLogger;

/**
 * Service responsible for delivering execution alerts through various channels
 * such as in-app notifications and email based on company communication settings.
 */
class ExecutionAlertChannelDeliveryService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly TransactionalEmailSender $transactionalEmailSender,
    ) {
    }

    /**
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     */
    public function deliver(array $alert, int $windowMinutes): void
    {
        // Validate the company ID from the alert
        $companyId = (int) ($alert['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        // Retrieve or create default communication settings for the company
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                CompanyCommunicationSetting::defaultAttributes()
            );

        // Determine eligible delivery channels based on settings
        $channels = $this->eligibleChannels($settings);
        if ($channels === []) {
            // Log that delivery was skipped due to no enabled channels
            $this->tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.execution.alert.notification.skipped',
                actor: null,
                description: 'Execution alert delivery skipped because no in-app/email channel is enabled.',
                metadata: array_merge($this->baseMetadata($alert, $windowMinutes), [
                    'reason' => 'no_supported_channels',
                ]),
            );

            return;
        }

        // Fetch active users with owner or finance roles as recipients
        $recipients = User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Owner->value, UserRole::Finance->value])
            ->get(['id', 'name', 'email']);

        if ($recipients->isEmpty()) {
            // Log failure due to no eligible recipients
            $this->tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.execution.alert.notification.failed',
                actor: null,
                description: 'Execution alert delivery failed because no owner/finance recipients were found.',
                metadata: array_merge($this->baseMetadata($alert, $windowMinutes), [
                    'reason' => 'no_recipients',
                ]),
            );

            return;
        }

        // Deliver the alert through each eligible channel
        foreach ($channels as $channel) {
            if ($channel === CompanyCommunicationSetting::CHANNEL_IN_APP) {
                $this->deliverInApp($companyId, $alert, $windowMinutes, $recipients->pluck('id')->all());

                continue;
            }

            if ($channel === CompanyCommunicationSetting::CHANNEL_EMAIL) {
                $this->deliverEmail($companyId, $alert, $windowMinutes, $recipients->all());
            }
        }
    }

    /**
     * @param  array<int, int|string>  $recipientIds
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     */
    private function deliverInApp(int $companyId, array $alert, int $windowMinutes, array $recipientIds): void
    {
        $baseMetadata = $this->baseMetadata($alert, $windowMinutes);

        // Create in-app notifications for each recipient
        $created = 0;
        foreach ($recipientIds as $recipientId) {
            $recipientId = (int) $recipientId;
            if ($recipientId <= 0) {
                continue;
            }

            ActivityLog::query()->create([
                'company_id' => $companyId,
                'user_id' => $recipientId,
                'action' => 'execution.alert.in_app',
                'entity_type' => 'tenant_execution_alert',
                'entity_id' => null,
                'metadata' => array_merge($baseMetadata, [
                    'recipient_user_id' => $recipientId,
                    'channel' => CompanyCommunicationSetting::CHANNEL_IN_APP,
                ]),
                'created_at' => now(),
            ]);

            $created++;
        }

        // Determine success or failure based on notifications created
        $action = $created > 0
            ? 'tenant.execution.alert.notification.sent'
            : 'tenant.execution.alert.notification.failed';

        $description = $created > 0
            ? 'Execution alert summary delivered via in-app notifications.'
            : 'Execution alert in-app delivery failed because no valid recipients were resolved.';

        // Log the delivery outcome
        $this->tenantAuditLogger->log(
            companyId: $companyId,
            action: $action,
            actor: null,
            description: $description,
            metadata: array_merge($baseMetadata, [
                'channel' => CompanyCommunicationSetting::CHANNEL_IN_APP,
                'recipient_count' => $created,
                'recipient_user_ids' => array_values(array_map('intval', $recipientIds)),
            ]),
        );
    }

    /**
     * @param  array<int, User>  $recipients
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     */
    private function deliverEmail(int $companyId, array $alert, int $windowMinutes, array $recipients): void
    {
        $sentRecipientIds = [];
        $missingEmailRecipientIds = [];
        $failedDeliveries = 0;

        // Attempt to send email to each recipient
        foreach ($recipients as $recipient) {
            $recipientId = (int) ($recipient->id ?? 0);
            $email = trim((string) ($recipient->email ?? ''));
            if ($email === '') {
                // Track recipients without email addresses
                if ($recipientId > 0) {
                    $missingEmailRecipientIds[] = $recipientId;
                }

                continue;
            }

            try {
                // Send the email using the transactional email sender
                $this->transactionalEmailSender->sendMailable($email, new ExecutionAlertMail($alert, $windowMinutes), [
                    'tags' => ['execution', (string) ($alert['type'] ?? 'alert')],
                ]);

                // Track successful sends
                if ($recipientId > 0) {
                    $sentRecipientIds[] = $recipientId;
                }
            } catch (\Throwable) {
                // Track failed deliveries
                $failedDeliveries++;
            }
        }

        // Log successful deliveries if any
        if ($sentRecipientIds !== []) {
            $this->tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.execution.alert.notification.sent',
                actor: null,
                description: 'Execution alert summary delivered via email notifications.',
                metadata: array_merge($this->baseMetadata($alert, $windowMinutes), [
                    'channel' => CompanyCommunicationSetting::CHANNEL_EMAIL,
                    'recipient_count' => count($sentRecipientIds),
                    'recipient_user_ids' => array_values($sentRecipientIds),
                    'missing_email_count' => count($missingEmailRecipientIds),
                ]),
            );
        }

        // Log failures if any occurred or no emails were sent
        if ($failedDeliveries > 0 || ($sentRecipientIds === [] && $missingEmailRecipientIds !== [])) {
            $this->tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.execution.alert.notification.failed',
                actor: null,
                description: 'Execution alert email delivery failed for one or more recipients.',
                metadata: array_merge($this->baseMetadata($alert, $windowMinutes), [
                    'channel' => CompanyCommunicationSetting::CHANNEL_EMAIL,
                    'failed_count' => $failedDeliveries,
                    'missing_email_count' => count($missingEmailRecipientIds),
                    'recipient_count' => count($sentRecipientIds),
                ]),
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function eligibleChannels(CompanyCommunicationSetting $settings): array
    {
        // Define supported delivery channels
        $supported = [
            CompanyCommunicationSetting::CHANNEL_IN_APP,
            CompanyCommunicationSetting::CHANNEL_EMAIL,
        ];

        // Get channels allowed by the settings
        $allowed = array_values(array_intersect($settings->selectableChannels(), $supported));
        // Get channels in the preferred order
        $ordered = array_values(array_intersect($settings->normalizedFallbackOrder(), $supported));

        // Build the list of eligible channels in order, without duplicates
        $channels = [];
        foreach ($ordered as $channel) {
            if (in_array($channel, $allowed, true) && ! in_array($channel, $channels, true)) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     * @return array<string,mixed>
     */
    private function baseMetadata(array $alert, int $windowMinutes): array
    {
        $alertType = (string) ($alert['type'] ?? '');

        // Build base metadata for logging and notifications
        $metadata = [
            'type' => $alertType,
            'pipeline' => (string) ($alert['pipeline'] ?? ''),
            'provider_key' => (string) ($alert['provider'] ?? ''),
            'count' => (int) ($alert['count'] ?? 0),
            'threshold' => (int) ($alert['threshold'] ?? 0),
            'window_minutes' => in_array($alertType, ['failure_spike', 'stuck_queued', 'invalid_webhook_spike'], true)
                ? $windowMinutes
                : 0,
            'trigger' => 'execution:ops:alert-summary',
        ];

        // Merge additional context if provided
        $context = (array) ($alert['context'] ?? []);

        return $context === [] ? $metadata : array_merge($metadata, $context);
    }
}
