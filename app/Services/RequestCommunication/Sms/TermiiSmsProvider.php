<?php

namespace App\Services\RequestCommunication\Sms;

use App\Services\RequestCommunication\DeliveryResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class TermiiSmsProvider implements SmsProvider
{
    public function send(string $to, string $message, array $context = []): DeliveryResult
    {
        $apiKey = trim((string) config('services.termii.api_key'));
        $senderId = trim((string) config('services.termii.sender_id'));
        $url = trim((string) config('services.termii.url', 'https://api.ng.termii.com/api/sms/send'));
        $channel = trim((string) config('services.termii.channel', 'generic'));

        if ($apiKey === '' || $senderId === '') {
            return DeliveryResult::failed('Termii SMS is not configured. Missing API key or sender ID.', [
                'provider' => 'termii',
            ]);
        }

        try {
            $response = Http::timeout(15)->post($url, [
                'to' => $to,
                'from' => $senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => $channel,
                'api_key' => $apiKey,
            ]);

            if (! $response->successful()) {
                return DeliveryResult::failed('Termii SMS request failed.', [
                    'provider' => 'termii',
                    'http_status' => $response->status(),
                    'response' => $response->json() ?: $response->body(),
                ]);
            }

            return DeliveryResult::sent('SMS delivered via Termii.', [
                'provider' => 'termii',
                'http_status' => $response->status(),
                'response' => $response->json() ?: $response->body(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('Termii SMS delivery failed.', [
                'provider' => 'termii',
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

