<?php

namespace App\Jobs\Execution;

use App\Services\Execution\SubscriptionBillingAttemptProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSubscriptionBillingAttemptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $attemptId,
    ) {
    }

    public function handle(SubscriptionBillingAttemptProcessor $processor): void
    {
        $processor->processAttemptById($this->attemptId);
    }
}