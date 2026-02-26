<?php

namespace App\Jobs;

use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Services\AssetCommunicationDeliveryManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssetCommunicationLog implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $logId
    ) {
    }

    public function handle(AssetCommunicationDeliveryManager $deliveryManager): void
    {
        $log = AssetCommunicationLog::query()->find($this->logId);
        if (! $log || (string) $log->status !== 'queued') {
            return;
        }

        $deliveryManager->deliver($log);
    }
}

