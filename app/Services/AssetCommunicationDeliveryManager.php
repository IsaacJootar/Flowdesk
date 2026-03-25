<?php

namespace App\Services;

use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Audit\Models\ActivityLog;
use App\Services\RequestCommunication\DeliveryResult;
use App\Services\RequestCommunication\Sms\SmsProvider;
use Throwable;

class AssetCommunicationDeliveryManager
{
    public function __construct(
        private readonly SmsProvider $smsProvider,
        private readonly TransactionalEmailSender $transactionalEmailSender,
    ) {
    }

    public function deliver(AssetCommunicationLog $log): void
    {
        if ((string) $log->status !== 'queued') {
            return;
        }

        $log->loadMissing([
            'asset:id,company_id,asset_code,name,status,maintenance_due_date,warranty_expires_at',
            'recipient:id,name,email,phone',
        ]);

        if (! $this->isChannelAllowedForCompany($log)) {
            $this->mark($log, DeliveryResult::failed('Channel is disabled or not configured for this organization.'));

            return;
        }

        try {
            $result = match ((string) $log->channel) {
                CompanyCommunicationSetting::CHANNEL_IN_APP => $this->deliverInApp($log),
                CompanyCommunicationSetting::CHANNEL_EMAIL => $this->deliverEmail($log),
                CompanyCommunicationSetting::CHANNEL_SMS => $this->deliverSms($log),
                default => DeliveryResult::failed('Unsupported communication channel.'),
            };
        } catch (Throwable $exception) {
            report($exception);
            $result = DeliveryResult::failed('Asset reminder delivery failed unexpectedly.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->mark($log, $result);
    }

    private function deliverInApp(AssetCommunicationLog $log): DeliveryResult
    {
        try {
            ActivityLog::query()->create([
                'company_id' => (int) $log->company_id,
                'user_id' => $log->recipient_user_id ? (int) $log->recipient_user_id : null,
                'action' => 'asset.notification.in_app',
                'entity_type' => 'asset_communication_log',
                'entity_id' => (int) $log->id,
                'metadata' => [
                    'event' => (string) $log->event,
                    'channel' => (string) $log->channel,
                    'asset_id' => (int) $log->asset_id,
                    'asset_code' => (string) ($log->asset?->asset_code ?? ''),
                    'recipient_user_id' => $log->recipient_user_id ? (int) $log->recipient_user_id : null,
                ],
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('In-app delivery failed.');
        }

        return DeliveryResult::sent('In-app notification delivered.');
    }

    private function deliverEmail(AssetCommunicationLog $log): DeliveryResult
    {
        $email = trim((string) ($log->recipient_email ?: $log->recipient?->email));
        if ($email === '') {
            return DeliveryResult::failed('Email delivery failed: recipient email is missing.');
        }

        $subject = $this->subjectForEvent((string) $log->event, (string) ($log->asset?->asset_code ?? 'N/A'));
        $body = $this->emailBody($log);

        try {
            $deliveryMetadata = $this->transactionalEmailSender->sendPlainText($email, $subject, $body, [
                'idempotency_key' => 'asset-communication-'.$log->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('Email delivery failed while sending.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return DeliveryResult::sent('Email reminder delivered.', $deliveryMetadata);
    }

    private function deliverSms(AssetCommunicationLog $log): DeliveryResult
    {
        $phone = trim((string) ($log->recipient_phone ?: $log->recipient?->phone));
        if ($phone === '') {
            return DeliveryResult::failed('SMS delivery failed: recipient phone is missing.');
        }

        $message = $this->smsBody($log);

        return $this->smsProvider->send($phone, $message, [
            'asset_communication_log_id' => (int) $log->id,
            'asset_id' => (int) $log->asset_id,
            'asset_code' => (string) ($log->asset?->asset_code ?? ''),
            'event' => (string) $log->event,
        ]);
    }

    private function isChannelAllowedForCompany(AssetCommunicationLog $log): bool
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $log->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return in_array((string) $log->channel, $settings->selectableChannels(), true);
    }

    private function subjectForEvent(string $event, string $assetCode): string
    {
        return match ($event) {
            'asset.internal.assignment.assigned' => "Flowdesk Asset Assigned - {$assetCode}",
            'asset.internal.assignment.transferred' => "Flowdesk Asset Transferred - {$assetCode}",
            'asset.internal.maintenance.overdue' => "Flowdesk Asset Maintenance Overdue - {$assetCode}",
            'asset.internal.maintenance.due_today' => "Flowdesk Asset Maintenance Due Today - {$assetCode}",
            'asset.internal.maintenance.due_soon' => "Flowdesk Asset Maintenance Due Soon - {$assetCode}",
            'asset.internal.warranty.expired' => "Flowdesk Asset Warranty Expired - {$assetCode}",
            'asset.internal.warranty.expires_today' => "Flowdesk Asset Warranty Expires Today - {$assetCode}",
            'asset.internal.warranty.expires_soon' => "Flowdesk Asset Warranty Expires Soon - {$assetCode}",
            default => "Flowdesk Asset Reminder - {$assetCode}",
        };
    }

    private function emailBody(AssetCommunicationLog $log): string
    {
        $recipientName = (string) ($log->recipient?->name ?? 'Team Member');
        $assetCode = (string) ($log->asset?->asset_code ?? 'N/A');
        $assetName = (string) ($log->asset?->name ?? 'Asset');
        $eventLabel = $this->eventLabel((string) $log->event);
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $referenceDate = (string) (($metadata['due_date'] ?? $metadata['event_date'] ?? null) ?: 'N/A');

        return trim(implode(PHP_EOL, [
            "Hello {$recipientName},",
            '',
            "Flowdesk asset reminder: {$eventLabel}.",
            "Asset: {$assetName} ({$assetCode})",
            "Reference Date: {$referenceDate}",
            '',
            'Please review and take action in Flowdesk.',
        ]));
    }

    private function smsBody(AssetCommunicationLog $log): string
    {
        $assetCode = (string) ($log->asset?->asset_code ?? 'N/A');
        $eventLabel = $this->eventLabel((string) $log->event);

        return "Flowdesk: {$eventLabel} for asset {$assetCode}.";
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'asset.internal.assignment.assigned' => 'asset assigned to you',
            'asset.internal.assignment.transferred' => 'asset transferred to you',
            'asset.internal.maintenance.overdue' => 'maintenance overdue',
            'asset.internal.maintenance.due_today' => 'maintenance due today',
            'asset.internal.maintenance.due_soon' => 'maintenance due soon',
            'asset.internal.warranty.expired' => 'warranty expired',
            'asset.internal.warranty.expires_today' => 'warranty expires today',
            'asset.internal.warranty.expires_soon' => 'warranty expires soon',
            default => 'asset reminder',
        };
    }

    private function mark(AssetCommunicationLog $log, DeliveryResult $result): void
    {
        $currentMetadata = is_array($log->metadata) ? $log->metadata : [];

        $log->forceFill([
            'status' => $result->status,
            'message' => $result->message,
            'metadata' => empty($result->metadata)
                ? $currentMetadata
                : array_merge($currentMetadata, ['delivery' => $result->metadata]),
            'sent_at' => $result->markSent ? now() : null,
        ])->save();
    }
}
