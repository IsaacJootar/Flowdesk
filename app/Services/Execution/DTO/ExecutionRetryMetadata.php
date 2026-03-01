<?php

namespace App\Services\Execution\DTO;

use Carbon\CarbonImmutable;

/**
 * Retry metadata attached to adapter responses for queue/dead-letter handling.
 */
final readonly class ExecutionRetryMetadata
{
    public function __construct(
        public int $attempt,
        public int $maxAttempts,
        public ?CarbonImmutable $nextRetryAt = null,
        public ?string $idempotencyKey = null,
    ) {
    }
}
