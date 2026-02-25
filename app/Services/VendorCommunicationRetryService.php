<?php

namespace App\Services;

use App\Domains\Vendors\Models\VendorCommunicationLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class VendorCommunicationRetryService
{
    public function __construct(
        private readonly VendorCommunicationDeliveryManager $deliveryManager
    ) {
    }

    public function retryLog(VendorCommunicationLog $log): VendorCommunicationLog
    {
        $this->prepareForRetry($log);
        $this->deliveryManager->deliver($log->fresh() ?? $log);

        return $log->fresh() ?? $log;
    }

    /**
     * @return array{retried:int,sent:int,failed:int,skipped:int}
     */
    public function retryFailed(?int $companyId = null, ?int $vendorId = null, int $batchSize = 200): array
    {
        $stats = ['retried' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        $this->failedQuery($companyId, $vendorId)
            ->limit(max(1, $batchSize))
            ->get()
            ->each(function (VendorCommunicationLog $log) use (&$stats): void {
                $stats['retried']++;
                $after = $this->retryLog($log);
                $this->incrementStatusCount($stats, (string) $after->status);
            });

        return $stats;
    }

    /**
     * @return array{processed:int,sent:int,failed:int,skipped:int,remaining_queued:int}
     */
    public function processStuckQueued(
        ?int $companyId = null,
        ?int $vendorId = null,
        int $olderThanMinutes = 2,
        int $batchSize = 500
    ): array {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining_queued' => 0];
        $cutoff = now()->subMinutes(max(0, $olderThanMinutes));

        $this->queuedQuery($companyId, $vendorId, $cutoff->toImmutable())
            ->limit(max(1, $batchSize))
            ->get()
            ->each(function (VendorCommunicationLog $log) use (&$stats): void {
                $stats['processed']++;

                if ((string) $log->status === 'queued') {
                    $this->deliveryManager->deliver($log);
                }

                $after = $log->fresh() ?? $log;
                $this->incrementStatusCount($stats, (string) $after->status);
            });

        $stats['remaining_queued'] = $this->queuedQuery($companyId, $vendorId, $cutoff->toImmutable())->count();

        return $stats;
    }

    private function prepareForRetry(VendorCommunicationLog $log): void
    {
        $metadata = is_array($log->metadata) ? $log->metadata : [];
        $retry = is_array($metadata['retry'] ?? null) ? $metadata['retry'] : [];
        $attempts = (int) ($retry['attempts'] ?? 0);

        $metadata['retry'] = [
            'attempts' => $attempts + 1,
            'last_attempted_at' => now()->toIso8601String(),
            'previous_status' => (string) $log->status,
            'previous_message' => (string) ($log->message ?? ''),
        ];

        $log->forceFill([
            'status' => 'queued',
            'message' => 'Retry queued.',
            'sent_at' => null,
            'metadata' => $metadata,
        ])->save();
    }

    private function incrementStatusCount(array &$stats, string $status): void
    {
        if (array_key_exists($status, $stats)) {
            $stats[$status] = (int) $stats[$status] + 1;
        }
    }

    private function failedQuery(?int $companyId, ?int $vendorId): Builder
    {
        return VendorCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($vendorId !== null, fn (Builder $query) => $query->where('vendor_id', $vendorId))
            ->where('status', 'failed')
            ->orderBy('id');
    }

    private function queuedQuery(?int $companyId, ?int $vendorId, CarbonImmutable $cutoff): Builder
    {
        return VendorCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($vendorId !== null, fn (Builder $query) => $query->where('vendor_id', $vendorId))
            ->where('status', 'queued')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id');
    }
}
