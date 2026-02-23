<?php

namespace App\Services\RequestCommunication\Sms;

use App\Services\RequestCommunication\DeliveryResult;

class NullSmsProvider implements SmsProvider
{
    public function send(string $to, string $message, array $context = []): DeliveryResult
    {
        // Placeholder SMS provider. Replace with a real provider implementation.
        return DeliveryResult::failed('SMS provider is not configured. Delivery skipped.', [
            'provider' => 'placeholder',
            'to' => $to,
        ]);
    }
}

