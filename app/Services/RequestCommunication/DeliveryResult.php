<?php

namespace App\Services\RequestCommunication;

class DeliveryResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $metadata = [],
        public readonly bool $markSent = false,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function sent(string $message, array $metadata = []): self
    {
        return new self('sent', $message, $metadata, true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function failed(string $message, array $metadata = []): self
    {
        return new self('failed', $message, $metadata, false);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function skipped(string $message, array $metadata = []): self
    {
        return new self('skipped', $message, $metadata, false);
    }
}

