<?php

namespace App\Services;

use App\Domains\Assets\Models\AssetCommunicationLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AssetCommunicationRetryService
{
    public function __construct(
        private readonly AssetCommunicationDeliveryManager $deliveryManager
    ) {
    }

    public function retryLog(AssetCommunicationLog $log): AssetCommunicationLog
    {
        $this->prepareForRetry($log);
        $this->deliveryManager->deliver($log->fresh() ?? $log);

        return $log->fresh() ?? $log;
    }

    /**
     * @return array{retried:int,sent:int,failed:int,skipped:int}
     */
    public function retryFailed(?int $companyId = null, int $batchSize = 200): array
    {
        $stats = ['retried' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        $this->failedQuery($companyId)
            ->limit(max(1, $batchSize))
            ->get()
            ->each(function (AssetCommunicationLog $log) use (&$stats): void {
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
        int $olderThanMinutes = 2,
        int $batchSize = 500
    ): array {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining_queued' => 0];
        $cutoff = now()->subMinutes(max(0, $olderThanMinutes));

        $this->queuedQuery($companyId, $cutoff->toImmutable())
            ->limit(max(1, $batchSize))
            ->get()
            ->each(function (AssetCommunicationLog $log) use (&$stats): void {
                $stats['processed']++;

                if ((string) $log->status === 'queued') {
                    $this->deliveryManager->deliver($log);
                }

                $after = $log->fresh() ?? $log;
                $this->incrementStatusCount($stats, (string) $after->status);
            });

        $stats['remaining_queued'] = $this->queuedQuery($companyId, $cutoff->toImmutable())->count();

        return $stats;
    }

    private function prepareForRetry(AssetCommunicationLog $log): void
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

    private function failedQuery(?int $companyId): Builder
    {
        return AssetCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->where('status', 'failed')
            ->orderBy('id');
    }

    private function queuedQuery(?int $companyId, CarbonImmutable $cutoff): Builder
    {
        return AssetCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->where('status', 'queued')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id');
    }
}

