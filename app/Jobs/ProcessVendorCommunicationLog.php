<?php

namespace App\Jobs;

use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Services\VendorCommunicationDeliveryManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVendorCommunicationLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $logId
    ) {
    }

    public function handle(VendorCommunicationDeliveryManager $deliveryManager): void
    {
        $log = VendorCommunicationLog::query()->find($this->logId);
        if (! $log || (string) $log->status !== 'queued') {
            return;
        }

        $deliveryManager->deliver($log);
    }
}
