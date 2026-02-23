<?php

namespace App\Services\RequestCommunication\Adapters;

use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Services\RequestCommunication\ChannelDeliveryAdapter;
use App\Services\RequestCommunication\DeliveryResult;
use Throwable;

class InAppChannelDeliveryAdapter implements ChannelDeliveryAdapter
{
    public function deliver(RequestCommunicationLog $log): DeliveryResult
    {
        try {
            ActivityLog::query()->create([
                'company_id' => (int) $log->company_id,
                'user_id' => $log->recipient_user_id ? (int) $log->recipient_user_id : null,
                'action' => 'request.notification.in_app',
                'entity_type' => 'request_communication_log',
                'entity_id' => (int) $log->id,
                'metadata' => [
                    'event' => (string) $log->event,
                    'channel' => (string) $log->channel,
                    'request_id' => (int) $log->request_id,
                    'request_code' => (string) ($log->request?->request_code ?? ''),
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
}

