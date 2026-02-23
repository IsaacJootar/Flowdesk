<?php

namespace App\Services\RequestCommunication\Adapters;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Services\RequestCommunication\ChannelDeliveryAdapter;
use App\Services\RequestCommunication\DeliveryResult;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailChannelDeliveryAdapter implements ChannelDeliveryAdapter
{
    public function deliver(RequestCommunicationLog $log): DeliveryResult
    {
        $recipient = $log->recipient;
        $email = $recipient?->email ? trim((string) $recipient->email) : '';

        if ($email === '') {
            return DeliveryResult::failed('Email delivery failed: recipient email is missing.');
        }

        $subject = $this->subjectFor($log);
        $body = $this->bodyFor($log);

        try {
            Mail::raw($body, function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::failed('Email delivery failed while sending.');
        }

        return DeliveryResult::sent('Email delivered.', [
            'to' => $email,
            'subject' => $subject,
        ]);
    }

    private function subjectFor(RequestCommunicationLog $log): string
    {
        $event = Str::of((string) $log->event)->replace('.', ' ')->headline()->value();
        $requestCode = (string) ($log->request?->request_code ?? 'N/A');

        return "Flowdesk {$event} - {$requestCode}";
    }

    private function bodyFor(RequestCommunicationLog $log): string
    {
        $requestCode = (string) ($log->request?->request_code ?? 'N/A');
        $event = Str::of((string) $log->event)->replace('.', ' ')->headline()->value();
        $title = (string) ($log->request?->title ?? '');

        return trim(implode(PHP_EOL, [
            "Event: {$event}",
            "Request: {$requestCode}",
            $title !== '' ? "Title: {$title}" : '',
            "Status: ".Str::of((string) ($log->request?->status ?? 'unknown'))->replace('_', ' ')->headline()->value(),
            '',
            'This message was sent by Flowdesk request approvals.',
        ]));
    }
}

