<?php

namespace App\Services\RequestCommunication\Sms;

use App\Services\RequestCommunication\DeliveryResult;

interface SmsProvider
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $to, string $message, array $context = []): DeliveryResult;
}

