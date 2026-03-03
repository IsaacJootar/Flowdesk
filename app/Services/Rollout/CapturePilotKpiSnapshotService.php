<?php

namespace App\Services\Rollout;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use App\Models\User;
use Illuminate\Support\Carbon;

class CapturePilotKpiSnapshotService
{
    /**
     * @return array{company_count:int,captured:int,window_label:string,window_start:string,window_end:string,rows:array<int,array<string,mixed>>}
     */
    public function captureWindow(
        ?int $companyId,
        string $windowLabel,
        Carbon $windowStart,
        Carbon $windowEnd,
        ?User $actor = null,
        ?string $notes = null,
    ): array {
        $windowLabel = $this->normalizeWindowLabel($windowLabel);
        $windowStart = $windowStart->copy()->startOfMinute();
        $windowEnd = $windowEnd->copy()->endOfMinute();

        if ($windowEnd->lessThan($windowStart)) {
            throw new \InvalidArgumentException('Window end must be greater than or equal to window start.');
        }

        $companyIds = $this->targetCompanyIds($companyId);

        $rows = [];
        foreach ($companyIds as $tenantCompanyId) {
            $rows[] = $this->captureForCompany(
                companyId: $tenantCompanyId,
                windowLabel: $windowLabel,
                windowStart: $windowStart,
                windowEnd: $windowEnd,
                actor: $actor,
                notes: $notes,
            );
        }

        return [
            'company_count' => count($companyIds),
            'captured' => count($rows),
            'window_label' => $windowLabel,
            'window_start' => $windowStart->toDateTimeString(),
            'window_end' => $windowEnd->toDateTimeString(),
            'rows' => $rows,
        ];
    }

    private function normalizeWindowLabel(string $windowLabel): string
    {
        $normalized = strtolower(trim($windowLabel));

        if ($normalized === '') {
            return 'pilot';
        }

        return in_array($normalized, ['baseline', 'pilot'], true)
            ? $normalized
            : 'custom';
    }

    /**
     * @return array<int, int>
     */
    private function targetCompanyIds(?int $companyId): array
    {
        if ($companyId && $companyId > 0) {
            $exists = Company::query()->whereKey($companyId)->exists();

            return $exists ? [$companyId] : [];
        }

        return Company::query()
            ->where('is_active', true)
            ->whereHas('featureEntitlements', function ($query): void {
                // Pilot KPI capture only targets tenants with both new lanes enabled.
                $query->where('procurement_enabled', true)
                    ->where('treasury_enabled', true);
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function captureForCompany(
        int $companyId,
        string $windowLabel,
        Carbon $windowStart,
        Carbon $windowEnd,
        ?User $actor,
        ?string $notes,
    ): array {
        $procurementMatch = $this->procurementMatchMetrics($companyId, $windowStart, $windowEnd);
        $procurementExceptions = $this->openProcurementExceptionMetrics($companyId, $windowEnd);
        $treasuryMatch = $this->autoReconciliationMetrics($companyId, $windowStart, $windowEnd);
        $treasuryExceptions = $this->openTreasuryExceptionMetrics($companyId, $windowEnd);
        $blockedPayoutCount = $this->auditActionCount(
            $companyId,
            ['tenant.execution.payout.blocked_by_procurement_match'],
            $windowStart,
            $windowEnd,
        );
        $manualOverrideCount = $this->auditActionCount(
            $companyId,
            [
                'tenant.procurement.match.exception.resolved',
                'tenant.procurement.match.exception.waived',
                'tenant.treasury.exception.resolved',
                'tenant.treasury.exception.waived',
            ],
            $windowStart,
            $windowEnd,
        );
        $incidentCount = $this->auditActionCount(
            $companyId,
            [
                'tenant.execution.alert.summary_emitted',
                'tenant.execution.billing.handoff_to_treasury',
                'tenant.execution.payout.handoff_to_treasury',
                'tenant.execution.webhook.handoff_to_treasury',
                'tenant.procurement.match.exception.action.denied',
                'tenant.treasury.exception.action.denied',
            ],
            $windowStart,
            $windowEnd,
        );

        $windowDays = max(1, $windowStart->diffInDays($windowEnd) + 1);
        $incidentRatePerWeek = round($incidentCount / ($windowDays / 7), 2);

        $record = TenantPilotKpiCapture::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'window_label' => $windowLabel,
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
            ],
            [
                'match_pass_rate_percent' => $procurementMatch['rate_percent'],
                'open_procurement_exceptions' => $procurementExceptions['count'],
                'procurement_exception_avg_open_hours' => $procurementExceptions['avg_open_hours'],
                'auto_reconciliation_rate_percent' => $treasuryMatch['rate_percent'],
                'open_treasury_exceptions' => $treasuryExceptions['count'],
                'treasury_exception_avg_open_hours' => $treasuryExceptions['avg_open_hours'],
                'blocked_payout_count' => $blockedPayoutCount,
                'manual_override_count' => $manualOverrideCount,
                'incident_count' => $incidentCount,
                'incident_rate_per_week' => $incidentRatePerWeek,
                'metadata' => [
                    'procurement_match' => $procurementMatch,
                    'procurement_open_exceptions' => $procurementExceptions,
                    'treasury_auto_match' => $treasuryMatch,
                    'treasury_open_exceptions' => $treasuryExceptions,
                ],
                'notes' => $notes,
                'captured_at' => now(),
                'captured_by_user_id' => $actor?->id,
            ]
        );

        TenantAuditEvent::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => $actor?->id,
            'action' => 'tenant.rollout.pilot_kpi_capture.recorded',
            'entity_type' => TenantPilotKpiCapture::class,
            'entity_id' => (int) $record->id,
            'description' => 'Pilot rollout KPI capture recorded for baseline vs pilot tracking.',
            'metadata' => [
                'window_label' => $windowLabel,
                'window_start' => $windowStart->toDateTimeString(),
                'window_end' => $windowEnd->toDateTimeString(),
            ],
            'event_at' => now(),
        ]);

        return [
            'id' => (int) $record->id,
            'company_id' => $companyId,
            'window_label' => $windowLabel,
            'match_pass_rate_percent' => (float) $record->match_pass_rate_percent,
            'open_procurement_exceptions' => (int) $record->open_procurement_exceptions,
            'auto_reconciliation_rate_percent' => (float) $record->auto_reconciliation_rate_percent,
            'open_treasury_exceptions' => (int) $record->open_treasury_exceptions,
            'blocked_payout_count' => (int) $record->blocked_payout_count,
            'manual_override_count' => (int) $record->manual_override_count,
            'incident_rate_per_week' => (float) $record->incident_rate_per_week,
        ];
    }

    /**
     * @return array{matched:int,mismatch:int,rate_percent:float}
     */
    private function procurementMatchMetrics(int $companyId, Carbon $windowStart, Carbon $windowEnd): array
    {
        $baseQuery = InvoiceMatchResult::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$windowStart, $windowEnd]);

        $matched = (clone $baseQuery)
            ->where('match_status', InvoiceMatchResult::STATUS_MATCHED)
            ->count();

        $mismatch = (clone $baseQuery)
            ->where('match_status', InvoiceMatchResult::STATUS_MISMATCH)
            ->count();

        $total = $matched + $mismatch;

        return [
            'matched' => $matched,
            'mismatch' => $mismatch,
            'rate_percent' => $total > 0 ? round(($matched / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array{count:int,avg_open_hours:float}
     */
    private function openProcurementExceptionMetrics(int $companyId, Carbon $windowEnd): array
    {
        $openRows = InvoiceMatchException::query()
            ->where('company_id', $companyId)
            ->where('created_at', '<=', $windowEnd)
            ->where(function ($query) use ($windowEnd): void {
                // We evaluate "open" as-of the window end so baseline and pilot snapshots are comparable.
                $query->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                    ->orWhere(function ($resolvedQuery) use ($windowEnd): void {
                        $resolvedQuery->whereNotNull('resolved_at')
                            ->where('resolved_at', '>', $windowEnd);
                    });
            })
            ->get(['created_at']);

        return [
            'count' => $openRows->count(),
            'avg_open_hours' => $this->averageOpenHours($openRows->pluck('created_at')->all(), $windowEnd),
        ];
    }

    /**
     * @return array{auto_matched:int,total_lines:int,rate_percent:float}
     */
    private function autoReconciliationMetrics(int $companyId, Carbon $windowStart, Carbon $windowEnd): array
    {
        $totalLines = BankStatementLine::query()
            ->where('company_id', $companyId)
            ->whereBetween('posted_at', [$windowStart, $windowEnd])
            ->count();

        $autoMatched = ReconciliationMatch::query()
            ->where('company_id', $companyId)
            ->where('matched_by', 'system')
            ->where('match_status', ReconciliationMatch::STATUS_MATCHED)
            ->where(function ($query) use ($windowStart, $windowEnd): void {
                $query->whereBetween('matched_at', [$windowStart, $windowEnd])
                    ->orWhere(function ($fallbackQuery) use ($windowStart, $windowEnd): void {
                        $fallbackQuery->whereNull('matched_at')
                            ->whereBetween('created_at', [$windowStart, $windowEnd]);
                    });
            })
            ->count();

        return [
            'auto_matched' => $autoMatched,
            'total_lines' => $totalLines,
            'rate_percent' => $totalLines > 0 ? round(($autoMatched / $totalLines) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array{count:int,avg_open_hours:float}
     */
    private function openTreasuryExceptionMetrics(int $companyId, Carbon $windowEnd): array
    {
        $openRows = ReconciliationException::query()
            ->where('company_id', $companyId)
            ->where('created_at', '<=', $windowEnd)
            ->where(function ($query) use ($windowEnd): void {
                // Same as procurement: age and open counts are point-in-time at window end.
                $query->where('exception_status', ReconciliationException::STATUS_OPEN)
                    ->orWhere(function ($resolvedQuery) use ($windowEnd): void {
                        $resolvedQuery->whereNotNull('resolved_at')
                            ->where('resolved_at', '>', $windowEnd);
                    });
            })
            ->get(['created_at']);

        return [
            'count' => $openRows->count(),
            'avg_open_hours' => $this->averageOpenHours($openRows->pluck('created_at')->all(), $windowEnd),
        ];
    }

    /**
     * @param  array<int, Carbon|string|null>  $createdAts
     */
    private function averageOpenHours(array $createdAts, Carbon $windowEnd): float
    {
        if ($createdAts === []) {
            return 0.0;
        }

        $totalHours = 0.0;
        $valid = 0;

        foreach ($createdAts as $createdAt) {
            if (! $createdAt) {
                continue;
            }

            $start = $createdAt instanceof Carbon ? $createdAt : Carbon::parse((string) $createdAt);
            $totalHours += max(0.0, $start->diffInSeconds($windowEnd) / 3600);
            $valid++;
        }

        if ($valid === 0) {
            return 0.0;
        }

        return round($totalHours / $valid, 2);
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function auditActionCount(int $companyId, array $actions, Carbon $windowStart, Carbon $windowEnd): int
    {
        return (int) TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->whereIn('action', $actions)
            ->whereBetween('event_at', [$windowStart, $windowEnd])
            ->count();
    }
}

