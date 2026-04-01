<?php

namespace App\Services;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Mail\VendorReminderMail;
use App\Services\RequestCommunication\DeliveryResult;
use App\Services\RequestCommunication\Sms\SmsProvider;
use Throwable;

class VendorCommunicationDeliveryManager
{
    public function __construct(
        private readonly SmsProvider $smsProvider,
        private readonly TransactionalEmailSender $transactionalEmailSender,
    ) {
    }

    public function deliver(VendorCommunicationLog $log): void
    {
        if ((string) $log->status !== 'queued') {
            return;
        }

        $log->loadMissing([
            'vendor:id,name,email,phone,company_id',
            'invoice:id,vendor_id,invoice_number,due_date,currency,outstanding_amount,status',
            'recipient:id,name,email,phone',
        ]);

        if (! $this->isChannelAllowedForCompany($log)) {
            $this->mark($log, DeliveryResult::failed('Channel is disabled or not configured for this organization.'));

            return;
        }

        try {
            $result = match ((string) $log->channel) {
                CompanyCommunicationSetting::CHANNEL_IN_APP => DeliveryResult::sent('In-app notification delivered.'),
                CompanyCommunicationSetting::CHANNEL_EMAIL => $this->sendEmail($log),
                CompanyCommunicationSetting::CHANNEL_SMS => $this->sendSms($log),
                default => DeliveryResult::failed('Unsupported communication channel.'),
            };
        } catch (Throwable $exception) {
            report($exception);
            $result = DeliveryResult::failed('Vendor reminder delivery failed unexpectedly.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->mark($log, $result);
    }

    private function isChannelAllowedForCompany(VendorCommunicationLog $log): bool
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $log->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return in_array((string) $log->channel, $settings->selectableChannels(), true);
    }

    private function sendEmail(VendorCommunicationLog $log): DeliveryResult
    {
        $email = trim((string) ($log->recipient_email ?: $log->recipient?->email ?: $log->vendor?->email));
        if ($email === '') {
            return DeliveryResult::failed('Email delivery failed: recipient email is missing.');
        }

        try {
            $deliveryMetadata = $this->transactionalEmailSender->sendMailable($email, new VendorReminderMail($log), [
                'idempotency_key' => 'vendor-communication-'.$log->id,
                'log_id' => (string) $log->id,
                'tags' => ['vendors', 'log-'.$log->id, (string) $log->event],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('Email delivery failed while sending.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return DeliveryResult::sent('Email reminder delivered.', $deliveryMetadata);
    }

    private function sendSms(VendorCommunicationLog $log): DeliveryResult
    {
        $phone = trim((string) ($log->recipient_phone ?: $log->recipient?->phone ?: $log->vendor?->phone));
        if ($phone === '') {
            return DeliveryResult::failed('SMS delivery failed: recipient phone is missing.');
        }

        $message = $this->smsBody($log);

        return $this->smsProvider->send($phone, $message, [
            'vendor_communication_log_id' => (int) $log->id,
            'vendor_id' => (int) $log->vendor_id,
            'vendor_invoice_id' => (int) $log->vendor_invoice_id,
            'event' => (string) $log->event,
        ]);
    }

    private function smsBody(VendorCommunicationLog $log): string
    {
        $invoiceNumber = (string) ($log->invoice?->invoice_number ?? 'N/A');
        $currency = strtoupper((string) ($log->invoice?->currency ?: 'NGN'));
        $outstanding = (int) ($log->invoice?->outstanding_amount ?? 0);
        $eventLabel = $this->eventLabel((string) $log->event);
        $vendorName = (string) ($log->vendor?->name ?? 'Vendor');

        return "Flowdesk {$eventLabel}: {$vendorName}, invoice {$invoiceNumber}, outstanding {$currency} ".number_format($outstanding, 2).'.';
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            // Legacy vendor-facing reminder labels (kept for backward compatibility with existing rows)
            'vendor.invoice.overdue.reminder' => 'overdue',
            'vendor.invoice.due_today.reminder' => 'due today',
            'vendor.invoice.due_soon.reminder' => 'due soon',
            'vendor.invoice.payment_recorded' => 'payment posted',
            // Internal finance-only reminders and notifications
            'vendor.internal.payment_recorded' => 'payment posted (internal)',
            'vendor.internal.overdue.reminder' => 'overdue',
            'vendor.internal.due_today.reminder' => 'due today',
            'vendor.internal.due_soon.reminder' => 'due soon',
            'vendor.invoice.voided' => 'invoice status update',
            'vendor.invoice.created' => 'invoice created',
            default => 'upcoming due',
        };
    }

    private function mark(VendorCommunicationLog $log, DeliveryResult $result): void
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
