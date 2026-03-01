<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ExecutionOpsAlertService
{
    /**
     * @return array{window_minutes:int,threshold:int,alerts:array<int,array{pipeline:string,provider:string,company_id:int,count:int}>}
     */
    public function emitWarnings(?int $windowMinutes = null): array
    {
        $windowMinutes = $windowMinutes ?? (int) config('execution.ops_alerts.window_minutes', 60);
        $threshold = max(1, (int) config('execution.ops_alerts.failure_threshold', 5));

        $summary = $this->summarizeFailures($windowMinutes, $threshold);

        foreach ($summary['alerts'] as $alert) {
            Log::warning('Execution operations center detected repeated failures.', [
                'pipeline' => $alert['pipeline'],
                'provider' => $alert['provider'],
                'company_id' => $alert['company_id'],
                'count' => $alert['count'],
                'window_minutes' => $summary['window_minutes'],
                'threshold' => $summary['threshold'],
            ]);
        }

        return $summary;
    }

    /**
     * @return array{window_minutes:int,threshold:int,alerts:array<int,array{pipeline:string,provider:string,company_id:int,count:int}>}
     */
    public function summarizeFailures(int $windowMinutes, int $threshold): array
    {
        $windowMinutes = max(1, $windowMinutes);
        $threshold = max(1, $threshold);
        $since = Carbon::now()->subMinutes($windowMinutes);

        $alerts = [];

        // Group by provider + tenant company so platform ops can identify hotspot combinations.
        $billingFailures = TenantSubscriptionBillingAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('attempt_status', 'failed')
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($billingFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'pipeline' => 'billing',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
            ];
        }

        $payoutFailures = RequestPayoutExecutionAttempt::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where('execution_status', 'failed')
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($payoutFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'pipeline' => 'payout',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
            ];
        }

        $webhookFailures = ExecutionWebhookEvent::query()
            ->selectRaw('provider_key, company_id, COUNT(*) as aggregate_count')
            ->where(function ($query): void {
                $query->where('processing_status', 'failed')
                    ->orWhere('verification_status', 'invalid');
            })
            ->where('updated_at', '>=', $since)
            ->groupBy('provider_key', 'company_id')
            ->get();

        foreach ($webhookFailures as $row) {
            if ((int) $row->aggregate_count < $threshold) {
                continue;
            }

            $alerts[] = [
                'pipeline' => 'webhook',
                'provider' => (string) $row->provider_key,
                'company_id' => (int) $row->company_id,
                'count' => (int) $row->aggregate_count,
            ];
        }

        return [
            'window_minutes' => $windowMinutes,
            'threshold' => $threshold,
            'alerts' => $alerts,
        ];
    }
}
