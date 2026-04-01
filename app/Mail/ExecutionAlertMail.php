<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ExecutionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{type:string,pipeline:string,provider:string,company_id:int,count:int,threshold:int,context?:array<string,mixed>}  $alert
     */
    public function __construct(
        public array $alert,
        public int $windowMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        $pipeline = Str::of((string) ($this->alert['pipeline'] ?? 'execution'))->replace('_', ' ')->headline()->value();
        $type = Str::of((string) ($this->alert['type'] ?? 'alert'))->replace('_', ' ')->headline()->value();

        return new Envelope(
            subject: "Flowdesk Execution Alert - {$pipeline} ({$type})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.execution-alert',
        );
    }
}
