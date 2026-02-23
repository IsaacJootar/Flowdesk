<?php

namespace App\Services;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Services\RequestCommunication\Adapters\EmailChannelDeliveryAdapter;
use App\Services\RequestCommunication\Adapters\InAppChannelDeliveryAdapter;
use App\Services\RequestCommunication\Adapters\SmsChannelDeliveryAdapter;
use App\Services\RequestCommunication\DeliveryResult;
use Throwable;

class RequestCommunicationDeliveryManager
{
    public function __construct(
        private readonly InAppChannelDeliveryAdapter $inAppAdapter,
        private readonly EmailChannelDeliveryAdapter $emailAdapter,
        private readonly SmsChannelDeliveryAdapter $smsAdapter,
    ) {
    }

    public function deliver(RequestCommunicationLog $log): void
    {
        if ((string) $log->status !== 'queued') {
            return;
        }

        $log->loadMissing([
            'request:id,company_id,request_code,status,title',
            'recipient:id,name,email,phone',
        ]);

        if (! $this->isChannelAllowedForCompany($log)) {
            $this->mark($log, DeliveryResult::failed('Channel is disabled or not configured for this organization.'));

            return;
        }

        try {
            $result = match ((string) $log->channel) {
                CompanyCommunicationSetting::CHANNEL_IN_APP => $this->inAppAdapter->deliver($log),
                CompanyCommunicationSetting::CHANNEL_EMAIL => $this->emailAdapter->deliver($log),
                CompanyCommunicationSetting::CHANNEL_SMS => $this->smsAdapter->deliver($log),
                default => DeliveryResult::failed('Unsupported communication channel.'),
            };
        } catch (Throwable $exception) {
            report($exception);
            $result = DeliveryResult::failed('Communication delivery failed unexpectedly.', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->mark($log, $result);
    }

    private function isChannelAllowedForCompany(RequestCommunicationLog $log): bool
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) $log->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return in_array((string) $log->channel, $settings->selectableChannels(), true);
    }

    private function mark(RequestCommunicationLog $log, DeliveryResult $result): void
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

