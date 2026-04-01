<?php

namespace App\Mail;

use App\Domains\Requests\Models\RequestCommunicationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RequestUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RequestCommunicationLog $log,
    ) {
    }

    public function envelope(): Envelope
    {
        $event = Str::of((string) $this->log->event)->replace('.', ' ')->headline()->value();
        $requestCode = (string) ($this->log->request?->request_code ?? 'N/A');

        return new Envelope(
            subject: "Flowdesk {$event} - {$requestCode}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.request-update',
        );
    }
}
