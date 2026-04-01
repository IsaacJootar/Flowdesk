<?php

namespace App\Services;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;
use App\Jobs\ProcessRequestCommunicationLog;
use Illuminate\Support\Facades\DB;

class RequestCommunicationLogger
{
    public function __construct(
        private readonly RequestCommunicationDeliveryManager $deliveryManager
    ) {
    }
    /**
     * @param  array<int, string>  $channels
     * @param  array<int, int>  $recipientUserIds
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        SpendRequest $request,
        string $event,
        array $channels,
        array $recipientUserIds = [],
        ?int $requestApprovalId = null,
        array $metadata = [],
        bool $forceQueue = false
    ): void {
        // Logger only writes queued records; actual delivery is handled by async jobs/adapters.
        $channels = array_values(array_unique(array_map('strval', $channels)));
        if ($channels === []) {
            return;
        }

        $recipientUserIds = array_values(array_unique(array_filter(array_map('intval', $recipientUserIds))));
        if ($recipientUserIds === []) {
            $this->createForAudience(
                request: $request,
                event: $event,
                channels: $channels,
                recipientUserId: null,
                requestApprovalId: $requestApprovalId,
                metadata: $metadata,
                forceQueue: $forceQueue
            );

            return;
        }

        foreach ($recipientUserIds as $recipientUserId) {
            $this->createForAudience(
                request: $request,
                event: $event,
                channels: $channels,
                recipientUserId: $recipientUserId,
                requestApprovalId: $requestApprovalId,
                metadata: $metadata,
                forceQueue: $forceQueue
            );
        }
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     */
    private function createForAudience(
        SpendRequest $request,
        string $event,
        array $channels,
        ?int $recipientUserId,
        ?int $requestApprovalId,
        array $metadata,
        bool $forceQueue
    ): void {
        foreach ($channels as $channel) {
            $log = RequestCommunicationLog::query()->create([
                'company_id' => (int) $request->company_id,
                'request_id' => (int) $request->id,
                'request_approval_id' => $requestApprovalId,
                'recipient_user_id' => $recipientUserId,
                'event' => $event,
                'channel' => $channel,
                'status' => 'queued',
                'message' => null,
                'metadata' => $metadata,
                'sent_at' => null,
            ]);

            $dispatch = function () use ($log, $forceQueue): void {
                if ($this->shouldQueue($forceQueue)) {
                    ProcessRequestCommunicationLog::dispatch((int) $log->id);
                    return;
                }

                $this->deliveryManager->deliver($log);
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        }
    }

    private function shouldQueue(bool $forceQueue): bool
    {
        if ($forceQueue) {
            return true;
        }

        return strtolower((string) config('communications.delivery.mode', 'inline')) === 'queue';
    }
}
