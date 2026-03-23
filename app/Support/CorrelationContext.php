<?php

namespace App\Support;

class CorrelationContext
{
    private ?string $correlationId = null;

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId !== null && trim($correlationId) !== ''
            ? trim($correlationId)
            : null;

        if ($this->correlationId !== null) {
            $this->context['correlation_id'] = $this->correlationId;
        } else {
            unset($this->context['correlation_id']);
        }
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function mergeContext(array $context): void
    {
        $this->context = array_filter(
            array_merge($this->context, $context),
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        if (isset($this->context['correlation_id'])) {
            $this->correlationId = (string) $this->context['correlation_id'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->context;
    }

    public function clear(): void
    {
        $this->correlationId = null;
        $this->context = [];
    }
}
