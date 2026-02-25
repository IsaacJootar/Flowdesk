<?php

namespace App\Jobs;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Services\RequestCommunicationDeliveryManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRequestCommunicationLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $logId
    ) {
    }

    public function handle(RequestCommunicationDeliveryManager $deliveryManager): void
    {
        $log = RequestCommunicationLog::query()->find($this->logId);
        if (! $log || (string) $log->status !== 'queued') {
            return;
        }

        $deliveryManager->deliver($log);
    }
}
