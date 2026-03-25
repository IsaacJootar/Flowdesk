<?php

namespace App\Services;

use App\Support\CorrelationContext;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class TransactionalEmailSender
{
    public function __construct(
        private readonly CorrelationContext $correlationContext,
    ) {
    }

    /**
     * Send a plain-text transactional email through the configured mailer.
     *
     * Flowdesk intentionally keeps queueing decisions outside this sender.
     * Request, vendor, asset, and execution workflows already decide whether
     * an email should run inline or inside an existing background job.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendPlainText(string $to, string $subject, string $body, array $options = []): array
    {
        $mailer = $this->mailerName();
        $idempotencyKey = trim((string) ($options['idempotency_key'] ?? ''));

        Mail::mailer($mailer)->raw($body, function (Message $message) use ($to, $subject, $idempotencyKey): void {
            $message->to($to)->subject($subject);

            $headers = $message->getSymfonyMessage()->getHeaders();
            $headers->addTextHeader('X-Flowdesk-Mailer', $this->mailerName());

            if ($this->correlationContext->correlationId() !== null) {
                $headers->addTextHeader('X-Correlation-ID', $this->correlationContext->correlationId());
            }

            // Resend SMTP supports idempotency keys through message headers.
            if ($idempotencyKey !== '') {
                $headers->addTextHeader('Resend-Idempotency-Key', $idempotencyKey);
            }
        });

        return array_filter([
            'to' => $to,
            'subject' => $subject,
            'mailer' => $mailer,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function mailerName(): string
    {
        $configured = trim((string) config('mail.transactional_mailer', ''));

        return $configured !== '' ? $configured : (string) config('mail.default', 'log');
    }
}
