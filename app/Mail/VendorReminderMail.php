<?php

namespace App\Mail;

use App\Domains\Vendors\Models\VendorCommunicationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VendorCommunicationLog $log,
    ) {
    }

    public function envelope(): Envelope
    {
        $invoiceNumber = (string) ($this->log->invoice?->invoice_number ?? 'N/A');
        $event = (string) $this->log->event;

        $subject = match ($event) {
            'vendor.invoice.payment_recorded' => "Flowdesk Payment Confirmation - {$invoiceNumber}",
            'vendor.internal.payment_recorded' => "Flowdesk Internal Payment Update - {$invoiceNumber}",
            'vendor.internal.overdue.reminder' => "Flowdesk Internal Overdue Alert - {$invoiceNumber}",
            'vendor.internal.due_today.reminder' => "Flowdesk Internal Due Today Alert - {$invoiceNumber}",
            'vendor.internal.due_soon.reminder' => "Flowdesk Internal Due Soon Alert - {$invoiceNumber}",
            default => "Flowdesk Invoice Notification - {$invoiceNumber}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor-reminder',
        );
    }
}
