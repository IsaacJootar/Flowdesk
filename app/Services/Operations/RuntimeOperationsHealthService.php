<?php

namespace App\Services\Operations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RuntimeOperationsHealthService
{
    public function recordSchedulerHeartbeat(): void
    {
        Cache::put(
            (string) config('observability.runtime.scheduler_heartbeat_cache_key', 'flowdesk:ops:scheduler-heartbeat-at'),
            now()->toIso8601String(),
            now()->addDay()
        );
    }

    /**
     * @return array{
     *   available:bool,
     *   scheduler_heartbeat_at:?string,
     *   scheduler_delay_minutes:?int,
     *   failed_jobs_total:int,
     *   failed_jobs_last_24h:int,
     *   queued_jobs_total:int,
     *   stale_jobs_total:int,
     *   note:?string
     * }
     */
    public function summary(): array
    {
        $heartbeatAt = $this->heartbeatAt();
        $heartbeatDelay = $heartbeatAt?->diffInMinutes(now());

        try {
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            $jobsTable = (string) config('queue.connections.database.table', 'jobs');
            $staleCutoff = now()->subMinutes(
                max(1, (int) config('execution.ops_recovery.older_than_minutes', 30))
            )->timestamp;

            return [
                'available' => true,
                'scheduler_heartbeat_at' => $heartbeatAt?->format('M d, Y H:i'),
                'scheduler_delay_minutes' => $heartbeatDelay,
                'failed_jobs_total' => (int) DB::table($failedTable)->count(),
                'failed_jobs_last_24h' => (int) DB::table($failedTable)
                    ->where('failed_at', '>=', now()->subDay())
                    ->count(),
                'queued_jobs_total' => (int) DB::table($jobsTable)->count(),
                'stale_jobs_total' => (int) DB::table($jobsTable)
                    ->where(function ($query) use ($staleCutoff): void {
                        $query->whereNull('reserved_at')
                            ->where('available_at', '<=', $staleCutoff);
                    })
                    ->count(),
                'note' => null,
            ];
        } catch (QueryException) {
            return [
                'available' => false,
                'scheduler_heartbeat_at' => $heartbeatAt?->format('M d, Y H:i'),
                'scheduler_delay_minutes' => $heartbeatDelay,
                'failed_jobs_total' => 0,
                'failed_jobs_last_24h' => 0,
                'queued_jobs_total' => 0,
                'stale_jobs_total' => 0,
                'note' => 'Queue health tables are not available in this environment yet.',
            ];
        }
    }

    private function heartbeatAt(): ?Carbon
    {
        $raw = Cache::get((string) config('observability.runtime.scheduler_heartbeat_cache_key', 'flowdesk:ops:scheduler-heartbeat-at'));

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
