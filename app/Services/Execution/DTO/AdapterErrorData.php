<?php

namespace App\Services\Execution\DTO;

/**
 * Standardized adapter error payload so retry/ops flows can inspect failures uniformly.
 */
final readonly class AdapterErrorData
{
    /**
     * @param  array<string,mixed>  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable = false,
        public ?string $providerReference = null,
        public array $details = [],
    ) {
    }
}
