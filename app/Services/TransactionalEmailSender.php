<?php

namespace App\Services;

use App\Support\CorrelationContext;
use Illuminate\Mail\Message;
use Illuminate\Mail\Mailable;
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
        $logId = trim((string) ($options['log_id'] ?? ''));
        $tags = $options['tags'] ?? [];

        Mail::mailer($mailer)->raw($body, function (Message $message) use ($to, $subject, $idempotencyKey, $logId, $tags, $mailer): void {
            $message->to($to)->subject($subject);

            $headers = $message->getSymfonyMessage()->getHeaders();
            $headers->addTextHeader('X-Flowdesk-Mailer', $mailer);

            if ($this->correlationContext->correlationId() !== null) {
                $headers->addTextHeader('X-Correlation-ID', $this->correlationContext->correlationId());
            }

            // Provider-agnostic idempotency header for safe retries.
            if ($idempotencyKey !== '') {
                $headers->addTextHeader('X-Flowdesk-Idempotency-Key', $idempotencyKey);
            }

            if ($logId !== '') {
                $headers->addTextHeader('X-Flowdesk-Log-Id', $logId);
            }

            if (is_array($tags) && $tags !== []) {
                $headers->addTextHeader('X-MailerSend-Tags', implode(',', array_map('strval', $tags)));
            }
        });

        return array_filter([
            'to' => $to,
            'subject' => $subject,
            'mailer' => $mailer,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'log_id' => $logId !== '' ? $logId : null,
            'tags' => is_array($tags) && $tags !== [] ? $tags : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Send a Mailable with Flowdesk correlation and tracking headers.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendMailable(string $to, Mailable $mailable, array $options = []): array
    {
        $mailer = $this->mailerName();
        $idempotencyKey = trim((string) ($options['idempotency_key'] ?? ''));
        $logId = trim((string) ($options['log_id'] ?? ''));
        $tags = $options['tags'] ?? [];

        $mailable->withSymfonyMessage(function ($symfonyMessage) use ($idempotencyKey, $logId, $tags, $mailer): void {
            $headers = $symfonyMessage->getHeaders();
            $headers->addTextHeader('X-Flowdesk-Mailer', $mailer);

            if ($this->correlationContext->correlationId() !== null) {
                $headers->addTextHeader('X-Correlation-ID', $this->correlationContext->correlationId());
            }

            if ($idempotencyKey !== '') {
                $headers->addTextHeader('X-Flowdesk-Idempotency-Key', $idempotencyKey);
            }

            if ($logId !== '') {
                $headers->addTextHeader('X-Flowdesk-Log-Id', $logId);
            }

            if (is_array($tags) && $tags !== []) {
                // MailerSend supports tag headers for SMTP deliveries.
                $headers->addTextHeader('X-MailerSend-Tags', implode(',', array_map('strval', $tags)));
            }
        });

        Mail::mailer($mailer)->to($to)->send($mailable);

        return array_filter([
            'to' => $to,
            'mailer' => $mailer,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'log_id' => $logId !== '' ? $logId : null,
            'tags' => is_array($tags) && $tags !== [] ? $tags : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function mailerName(): string
    {
        $configured = trim((string) config('mail.transactional_mailer', ''));

        return $configured !== '' ? $configured : (string) config('mail.default', 'log');
    }
}
