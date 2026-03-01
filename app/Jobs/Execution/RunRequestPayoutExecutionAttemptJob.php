<?php

namespace App\Jobs\Execution;

use App\Services\Execution\RequestPayoutExecutionAttemptProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunRequestPayoutExecutionAttemptJob implements ShouldQueue
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

    public function handle(RequestPayoutExecutionAttemptProcessor $processor): void
    {
        $processor->processAttemptById($this->attemptId);
    }
}