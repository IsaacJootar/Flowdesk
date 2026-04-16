<?php

namespace App\Services\Vendors;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service for building vendor command center data, including lanes for profile hygiene,
 * invoice follow-up, blocked handoffs, and failed retries.
 */
class VendorCommandCenterService
{
    private const LANE_LIMIT = 8;

    /**
     * Build the complete desk data for the vendor command center.
     * Includes summary statistics and lanes with actionable items.
     *
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,array<int,array<string,mixed>>>}
     */
    public function buildDeskData(User $user, bool $procurementEnabled, bool $requestsEnabled, string $search = ''): array
    {
        // Build data for each lane
        $profileLane = $this->profileHygieneLane($user, $search);
        $invoiceLane = $this->invoiceAndStatementLane($user, $search);
        $blockedLane = $procurementEnabled && $requestsEnabled
            ? $this->blockedHandoffLane($user, $search)
            : ['count' => 0, 'rows' => []];
        $failedLane = $requestsEnabled
            ? $this->failedRetryLane($user, $search)
            : ['count' => 0, 'rows' => []];

        $companyId = (int) $user->company_id;

        // Gather summary statistics
        $totalVendors = (int) Vendor::query()
            ->where('company_id', $companyId)
            ->count();

        $activeVendors = (int) Vendor::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $partPaidCount = (int) VendorInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', VendorInvoice::STATUS_PART_PAID)
            ->where('outstanding_amount', '>', 0)
            ->count();

        $overdueCount = (int) VendorInvoice::query()
            ->where('company_id', $companyId)
            ->where('outstanding_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        // Build workload summary for lanes
        $workload = $this->buildWorkloadSummary([
            ['key' => 'profile_hygiene', 'label' => 'Profile Hygiene', 'count' => $profileLane['count'], 'tone' => 'sky'],
            ['key' => 'invoice_follow_up', 'label' => 'Invoice Follow-up', 'count' => $invoiceLane['count'], 'tone' => 'amber'],
            ['key' => 'blocked_handoff', 'label' => 'Blocked Handoff', 'count' => $blockedLane['count'], 'tone' => 'rose'],
            ['key' => 'failed_retries', 'label' => 'Failed Payment Retries', 'count' => $failedLane['count'], 'tone' => 'indigo'],
        ]);

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'total_vendors' => $totalVendors,
                'active_vendors' => $activeVendors,
                'open_invoices' => $invoiceLane['count'],
                'part_paid' => $partPaidCount,
                'overdue' => $overdueCount,
                'blocked_handoff' => $blockedLane['count'],
                'failed_retries' => $failedLane['count'],
                ...$workload,
            ],
            'lanes' => [
                'profile_hygiene' => $profileLane['rows'],
                'invoice_follow_up' => $invoiceLane['rows'],
                'blocked_handoff' => $blockedLane['rows'],
                'failed_retries' => $failedLane['rows'],
            ],
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function profileHygieneLane(User $user, string $search): array
    {
        $query = Vendor::query()
            ->where('company_id', (int) $user->company_id)
            ->where(function (Builder $builder): void {
                // Missing profile data often causes payout handoff failures and manual reversals.
                $builder
                    ->whereNull('bank_name')
                    ->orWhere('bank_name', '')
                    ->orWhereNull('bank_code')
                    ->orWhere('bank_code', '')
                    ->orWhereNull('account_name')
                    ->orWhere('account_name', '')
                    ->orWhereNull('account_number')
                    ->orWhere('account_number', '')
                    ->orWhereNull('contact_person')
                    ->orWhere('contact_person', '')
                    ->orWhereNull('email')
                    ->orWhere('email', '');
            })
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('vendor_type', 'like', '%'.$search.'%');
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'name', 'vendor_type', 'bank_name', 'bank_code', 'account_name', 'account_number', 'contact_person', 'email'])
            ->map(function (Vendor $vendor): array {
                $issues = [];
                if (! $vendor->bank_name || ! $vendor->bank_code || ! $vendor->account_name || ! $vendor->account_number) {
                    $issues[] = 'bank details';
                }

                if (! $vendor->contact_person || ! $vendor->email) {
                    $issues[] = 'contact info';
                }

                return [
                    'ref' => (string) $vendor->name,
                    'title' => $vendor->vendor_type ? ucfirst((string) $vendor->vendor_type) : 'Uncategorized vendor',
                    'meta' => 'Missing: '.implode(', ', $issues),
                    'status' => 'Profile update needed',
                    'context' => 'Complete vendor profile data before invoice settlement and payout handoff.',
                    'next_action_label' => 'Open Vendor Profile',
                    'next_action_url' => route('vendors.show', ['vendor' => (int) $vendor->id]),
                    'next_action_tone' => 'sky',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function invoiceAndStatementLane(User $user, string $search): array
    {
        $query = VendorInvoice::query()
            ->with('vendor:id,name')
            ->where('company_id', (int) $user->company_id)
            ->where('outstanding_amount', '>', 0)
            ->whereIn('status', [
                VendorInvoice::STATUS_UNPAID,
                VendorInvoice::STATUS_PART_PAID,
                VendorInvoice::STATUS_OVERDUE,
            ])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'vendor_id', 'invoice_number', 'currency', 'outstanding_amount', 'due_date', 'status'])
            ->map(function (VendorInvoice $invoice): array {
                $dueLabel = $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A';
                $isOverdue = $invoice->due_date !== null && now()->startOfDay()->greaterThan($invoice->due_date);

                return [
                    'ref' => (string) $invoice->invoice_number,
                    'title' => (string) ($invoice->vendor?->name ?? 'Vendor invoice'),
                    'meta' => sprintf(
                        'Outstanding %s %s | Due %s',
                        strtoupper((string) ($invoice->currency ?: 'NGN')),
                        number_format((int) $invoice->outstanding_amount),
                        $dueLabel
                    ),
                    'status' => $isOverdue ? 'Overdue invoice' : 'Awaiting payment',
                    'context' => 'Track statement line and payment progress from the vendor profile timeline.',
                    'next_action_label' => 'Open Vendor Profile',
                    'next_action_url' => route('vendors.show', ['vendor' => (int) ($invoice->vendor_id ?? 0)]),
                    'next_action_tone' => 'amber',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function blockedHandoffLane(User $user, string $search): array
    {
        $query = SpendRequest::query()
            ->with('vendor:id,name')
            ->where('company_id', (int) $user->company_id)
            ->whereNotNull('vendor_id')
            ->whereIn('status', ['approved_for_execution', 'execution_queued', 'execution_processing', 'failed'])
            ->where(function (Builder $blockedQuery): void {
                $this->applyBlockedMetadataFilter($blockedQuery);
            })
            ->latest('updated_at')
            ->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'request_code', 'title', 'vendor_id', 'currency', 'approved_amount', 'amount', 'metadata'])
            ->map(function (SpendRequest $request): array {
                $reason = trim((string) data_get((array) ($request->metadata ?? []), 'execution.procurement_gate.reason', 'Resolve procurement mismatch blockers and retry payout queueing.'));

                return [
                    'ref' => (string) $request->request_code,
                    'title' => (string) ($request->vendor?->name ?? 'Vendor-linked request'),
                    'meta' => sprintf(
                        '%s %s',
                        strtoupper((string) ($request->currency ?: 'NGN')),
                        number_format((int) ($request->approved_amount ?: $request->amount ?: 0))
                    ),
                    'status' => 'Blocked handoff',
                    'context' => $reason,
                    'next_action_label' => 'Resolve in Vendor Payables',
                    'next_action_url' => route('operations.vendor-payables-desk', ['search' => (string) $request->request_code]),
                    'next_action_tone' => 'rose',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function failedRetryLane(User $user, string $search): array
    {
        $query = RequestPayoutExecutionAttempt::query()
            ->with('request.vendor:id,name')
            ->where('company_id', (int) $user->company_id)
            ->where('execution_status', 'failed')
            ->whereHas('request', function (Builder $builder): void {
                $builder->whereNotNull('vendor_id');
            })
            ->latest('failed_at')
            ->latest('id');

        if ($search !== '') {
            $query->whereHas('request', function (Builder $builder) use ($search): void {
                $builder
                    ->where('request_code', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', '%'.$search.'%'));
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'request_id', 'currency_code', 'amount', 'error_message'])
            ->map(function (RequestPayoutExecutionAttempt $attempt): array {
                $request = $attempt->request;

                return [
                    'ref' => (string) ($request?->request_code ?? 'N/A'),
                    'title' => (string) ($request?->vendor?->name ?? 'Vendor-linked payout attempt'),
                    'meta' => sprintf(
                        '%s %s',
                        strtoupper((string) ($attempt->currency_code ?: 'NGN')),
                        number_format((float) ($attempt->amount ?? 0), 2)
                    ),
                    'status' => 'Retry required',
                    'context' => trim((string) ($attempt->error_message ?: 'Check provider/config/state and retry.')),
                    'next_action_label' => 'Payments Ready to Send',
                    'next_action_url' => route('execution.payout-ready', ['search' => (string) ($request?->request_code ?? '')]),
                    'next_action_tone' => 'indigo',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private function applyBlockedMetadataFilter(Builder $query): void
    {
        // Keep the JSON boolean filter permissive because sqlite and mysql serialize booleans differently.
        $query->where(function (Builder $blockedQuery): void {
            $blockedQuery
                ->where('metadata->execution->procurement_gate->blocked', true)
                ->orWhere('metadata->execution->procurement_gate->blocked', 1)
                ->orWhere('metadata->execution->procurement_gate->blocked', 'true');
        });
    }

    /**
     * @param  array<int, array{key:string,label:string,count:int,tone:string}>  $segments
     * @return array{workload_total:int,bottleneck_label:string,bottleneck_count:int,segments:array<int,array{key:string,label:string,count:int,percent:float,tone:string}>}
     */
    private function buildWorkloadSummary(array $segments): array
    {
        $workloadTotal = array_sum(array_map(static fn (array $segment): int => (int) ($segment['count'] ?? 0), $segments));

        $bottleneckLabel = 'No blockers';
        $bottleneckCount = 0;

        $normalizedSegments = array_map(function (array $segment) use ($workloadTotal, &$bottleneckLabel, &$bottleneckCount): array {
            $count = (int) ($segment['count'] ?? 0);
            $percent = $workloadTotal > 0
                ? round(($count / $workloadTotal) * 100, 2)
                : 0.0;

            if ($count > $bottleneckCount) {
                $bottleneckCount = $count;
                $bottleneckLabel = (string) ($segment['label'] ?? 'No blockers');
            }

            return [
                'key' => (string) ($segment['key'] ?? 'segment'),
                'label' => (string) ($segment['label'] ?? 'Segment'),
                'count' => $count,
                'percent' => $percent,
                'tone' => (string) ($segment['tone'] ?? 'slate'),
            ];
        }, $segments);

        return [
            'workload_total' => $workloadTotal,
            'bottleneck_label' => $bottleneckLabel,
            'bottleneck_count' => $bottleneckCount,
            'segments' => $normalizedSegments,
        ];
    }
}
