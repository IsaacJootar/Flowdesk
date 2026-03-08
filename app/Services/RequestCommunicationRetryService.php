<?php

namespace App\Services;

use App\Domains\Requests\Models\RequestCommunicationLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RequestCommunicationRetryService
{
    public function __construct(
        private readonly RequestCommunicationDeliveryManager $deliveryManager
    ) {
    }

    /**
     * Retry one communication log.
     */
    public function retryLog(RequestCommunicationLog $log): RequestCommunicationLog
    {
        $this->prepareForRetry($log);
        $this->deliveryManager->deliver($log->fresh() ?? $log);

        return $log->fresh() ?? $log;
    }

    /**
     * Retry all failed logs in scope.
     *
     * @return array{retried:int, sent:int, failed:int, skipped:int}
     */
    public function retryFailed(?int $companyId = null, int $batchSize = 200): array
    {
        $stats = ['retried' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $batchLimit = $this->normalizeBatchSize($batchSize, (int) config('communications.recovery.default_retry_failed_batch', 200));

        $this->processQueryInChunks(
            $this->failedQuery($companyId),
            $batchLimit,
            function (RequestCommunicationLog $log) use (&$stats): void {
                $stats['retried']++;
                $after = $this->retryLog($log);
                $this->incrementStatusCount($stats, (string) $after->status);
            }
        );

        return $stats;
    }

    /**
     * Process queued logs older than the threshold.
     *
     * @return array{processed:int, sent:int, failed:int, skipped:int, remaining_queued:int}
     */
    public function processStuckQueued(
        ?int $companyId = null,
        int $olderThanMinutes = 2,
        int $batchSize = 500
    ): array {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining_queued' => 0];
        $olderThan = $this->normalizeOlderThanMinutes($olderThanMinutes);
        $batchLimit = $this->normalizeBatchSize($batchSize, (int) config('communications.recovery.default_process_queued_batch', 500));
        $cutoff = now()->subMinutes($olderThan);

        // Backlog worker: re-attempt queued rows that are older than an operational threshold.
        $this->processQueryInChunks(
            $this->queuedQuery($companyId, $cutoff->toImmutable()),
            $batchLimit,
            function (RequestCommunicationLog $log) use (&$stats): void {
                $stats['processed']++;

                if ((string) $log->status === 'queued') {
                    $this->deliveryManager->deliver($log);
                }

                $after = $log->fresh() ?? $log;
                $this->incrementStatusCount($stats, (string) $after->status);
            }
        );

        $stats['remaining_queued'] = $this->queuedQuery($companyId, $cutoff->toImmutable())->count();

        return $stats;
    }

    /**
     * @return array{failed:int, queued:int}
     */
    public function summary(?int $companyId = null, int $olderThanMinutes = 2): array
    {
        $cutoff = now()->subMinutes($this->normalizeOlderThanMinutes($olderThanMinutes));

        return [
            'failed' => $this->failedQuery($companyId)->count(),
            'queued' => $this->queuedQuery($companyId, $cutoff->toImmutable())->count(),
        ];
    }

    private function prepareForRetry(RequestCommunicationLog $log): void
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
        return RequestCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->where('status', 'failed');
    }

    private function queuedQuery(?int $companyId, CarbonImmutable $cutoff): Builder
    {
        return RequestCommunicationLog::query()
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->where('status', 'queued')
            ->where('created_at', '<=', $cutoff);
    }

    /**
     * @param  callable(RequestCommunicationLog):void  $callback
     */
    private function processQueryInChunks(Builder $query, int $batchLimit, callable $callback): void
    {
        $processed = 0;
        $chunkSize = $this->chunkSize();

        $query->chunkById($chunkSize, function ($logs) use (&$processed, $batchLimit, $callback): bool {
            foreach ($logs as $log) {
                if ($processed >= $batchLimit) {
                    return false;
                }

                if ($log instanceof RequestCommunicationLog) {
                    $callback($log);
                    $processed++;
                }
            }

            return $processed < $batchLimit;
        });
    }

    private function normalizeBatchSize(int $batchSize, int $default): int
    {
        $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));
        $candidate = $batchSize > 0 ? $batchSize : $default;

        return min($maxBatch, max(1, $candidate));
    }

    private function normalizeOlderThanMinutes(int $olderThanMinutes): int
    {
        $maxOlderThan = max(0, (int) config('communications.recovery.max_older_than_minutes', 10080));

        return min($maxOlderThan, max(0, $olderThanMinutes));
    }

    private function chunkSize(): int
    {
        $configured = (int) config('communications.recovery.chunk_size', 100);
        $maxBatch = max(1, (int) config('communications.recovery.max_batch_size', 500));

        return min($maxBatch, max(1, $configured));
    }
}
