<?php

namespace App\Services;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;

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
        array $metadata = []
    ): void {
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
                metadata: $metadata
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
                metadata: $metadata
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
        array $metadata
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

            $this->deliveryManager->deliver($log);
        }
    }
}
