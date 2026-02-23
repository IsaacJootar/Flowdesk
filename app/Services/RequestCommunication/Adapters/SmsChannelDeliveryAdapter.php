<?php

namespace App\Services\RequestCommunication\Adapters;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Services\RequestCommunication\ChannelDeliveryAdapter;
use App\Services\RequestCommunication\DeliveryResult;
use App\Services\RequestCommunication\Sms\SmsProvider;
use Illuminate\Support\Str;

class SmsChannelDeliveryAdapter implements ChannelDeliveryAdapter
{
    public function __construct(
        private readonly SmsProvider $smsProvider
    ) {
    }

    public function deliver(RequestCommunicationLog $log): DeliveryResult
    {
        $recipient = $log->recipient;
        $phone = $recipient?->phone ? trim((string) $recipient->phone) : '';

        if ($phone === '') {
            return DeliveryResult::failed('SMS delivery failed: recipient phone is missing.');
        }

        $message = $this->messageFor($log);

        return $this->smsProvider->send($phone, $message, [
            'request_communication_log_id' => (int) $log->id,
            'request_id' => (int) $log->request_id,
            'request_code' => (string) ($log->request?->request_code ?? ''),
            'event' => (string) $log->event,
        ]);
    }

    private function messageFor(RequestCommunicationLog $log): string
    {
        $event = Str::of((string) $log->event)->replace('.', ' ')->headline()->value();
        $requestCode = (string) ($log->request?->request_code ?? 'N/A');
        $status = Str::of((string) ($log->request?->status ?? 'unknown'))->replace('_', ' ')->headline()->value();

        return "Flowdesk: {$event}. Request {$requestCode}. Status {$status}.";
    }
}

