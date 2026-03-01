<?php

namespace App\Services\Execution\DTO;

/**
 * Shared operation result structure consumed by orchestration and operations center.
 */
final readonly class AdapterOperationResult
{
    /**
     * @param  array<string,mixed>  $raw
     */
    public function __construct(
        public AdapterOperationStatus $status,
        public bool $success,
        public ?string $providerReference = null,
        public array $raw = [],
        public ?AdapterErrorData $error = null,
        public ?ExecutionRetryMetadata $retry = null,
    ) {
    }
}
