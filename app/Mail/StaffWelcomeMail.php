<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public string $companyName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->companyName} on Flowdesk",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-welcome',
        );
    }
}
