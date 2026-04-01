<?php

namespace App\Services\RequestCommunication\Adapters;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Mail\RequestUpdateMail;
use App\Services\RequestCommunication\ChannelDeliveryAdapter;
use App\Services\RequestCommunication\DeliveryResult;
use App\Services\TransactionalEmailSender;
use Throwable;

class EmailChannelDeliveryAdapter implements ChannelDeliveryAdapter
{
    public function __construct(
        private readonly TransactionalEmailSender $transactionalEmailSender,
    ) {
    }

    public function deliver(RequestCommunicationLog $log): DeliveryResult
    {
        $recipient = $log->recipient;
        $email = $recipient?->email ? trim((string) $recipient->email) : '';

        if ($email === '') {
            return DeliveryResult::failed('Email delivery failed: recipient email is missing.');
        }

        try {
            $deliveryMetadata = $this->transactionalEmailSender->sendMailable($email, new RequestUpdateMail($log), [
                'idempotency_key' => 'request-communication-'.$log->id,
                'log_id' => (string) $log->id,
                'tags' => ['requests', 'log-'.$log->id, (string) $log->event],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('Email delivery failed while sending.');
        }

        return DeliveryResult::sent('Email delivered.', $deliveryMetadata);
    }
}
