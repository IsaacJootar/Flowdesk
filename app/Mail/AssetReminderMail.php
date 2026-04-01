<?php

namespace App\Mail;

use App\Domains\Assets\Models\AssetCommunicationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AssetCommunicationLog $log,
    ) {
    }

    public function envelope(): Envelope
    {
        $assetCode = (string) ($this->log->asset?->asset_code ?? 'N/A');
        $event = (string) $this->log->event;

        $subject = match ($event) {
            'asset.internal.assignment.assigned' => "Flowdesk Asset Assigned - {$assetCode}",
            'asset.internal.assignment.transferred' => "Flowdesk Asset Transferred - {$assetCode}",
            'asset.internal.maintenance.overdue' => "Flowdesk Asset Maintenance Overdue - {$assetCode}",
            'asset.internal.maintenance.due_today' => "Flowdesk Asset Maintenance Due Today - {$assetCode}",
            'asset.internal.maintenance.due_soon' => "Flowdesk Asset Maintenance Due Soon - {$assetCode}",
            'asset.internal.warranty.expired' => "Flowdesk Asset Warranty Expired - {$assetCode}",
            'asset.internal.warranty.expires_today' => "Flowdesk Asset Warranty Expires Today - {$assetCode}",
            'asset.internal.warranty.expires_soon' => "Flowdesk Asset Warranty Expires Soon - {$assetCode}",
            default => "Flowdesk Asset Reminder - {$assetCode}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.asset-reminder',
        );
    }
}
