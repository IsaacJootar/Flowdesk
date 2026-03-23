<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class FlowdeskLogContext
{
    /**
     * Keep log enrichment behind one helper so request, queue, and console code
     * all attach the same keys without duplicating logger-specific logic.
     *
     * @param  array<string, mixed>  $context
     */
    public function share(array $context): void
    {
        $context = array_filter($context, static fn (mixed $value): bool => $value !== null && $value !== '');

        $logger = Log::getFacadeRoot();

        if (is_object($logger) && method_exists($logger, 'shareContext')) {
            $logger->shareContext($context);

            return;
        }

        Log::withContext($context);
    }
}
