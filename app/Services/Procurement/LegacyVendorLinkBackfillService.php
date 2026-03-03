<?php

namespace App\Services\Procurement;

use App\Domains\Company\Models\Company;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;

class LegacyVendorLinkBackfillService
{
    public function __construct(
        private readonly EvaluateInvoiceThreeWayMatchService $evaluateInvoiceThreeWayMatchService,
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    /**
     * @return array{
     *   dry_run:bool,
     *   company_scope:int|null,
     *   settings:array<string,mixed>,
     *   companies_scanned:int,
     *   invoices:array<string,int>,
     *   payments:array<string,int>
     * }
     */
    public function run(
        ?int $companyId,
        bool $dryRun,
        int $batchSize,
        int $dateWindowDays,
        float $amountTolerancePercent,
        bool $recomputeMatch,
        bool $syncPayments,
    ): array {
        $summary = [
            'dry_run' => $dryRun,
            'company_scope' => $companyId,
            'settings' => [
                'batch_size' => $batchSize,
                'date_window_days' => $dateWindowDays,
                'amount_tolerance_percent' => $amountTolerancePercent,
                'recompute_match' => $recomputeMatch,
                'sync_payments' => $syncPayments,
            ],
            'companies_scanned' => 0,
            'invoices' => [
                'already_linked' => 0,
                'scanned' => 0,
                'eligible' => 0,
                'linked' => 0,
                'ambiguous' => 0,
                'no_candidate' => 0,
                'match_passed' => 0,
                'match_failed' => 0,
                'match_skipped_no_actor' => 0,
                'errors' => 0,
            ],
            'payments' => [
                'scanned' => 0,
                'mismatch_found' => 0,
                'synced' => 0,
                'errors' => 0,
            ],
        ];

        $companyIds = $this->companyIdsToProcess($companyId);

        foreach ($companyIds as $currentCompanyId) {
            $summary['companies_scanned']++;

            $actor = $this->resolveActor($currentCompanyId);
            $company = Company::query()->find($currentCompanyId);

            $summary['invoices']['already_linked'] += VendorInvoice::query()
                ->where('company_id', $currentCompanyId)
                ->whereNotNull('purchase_order_id')
                ->count();

            VendorInvoice::query()
                ->where('company_id', $currentCompanyId)
                ->whereNull('purchase_order_id')
                ->orderBy('id')
                ->chunkById(max(1, $batchSize), function ($invoices) use (
                    &$summary,
                    $dryRun,
                    $dateWindowDays,
                    $amountTolerancePercent,
                    $recomputeMatch,
                    $actor,
                    $company,
                    $currentCompanyId,
                ): void {
                    foreach ($invoices as $invoice) {
                        $summary['invoices']['scanned']++;

                        if (! $invoice instanceof VendorInvoice) {
                            continue;
                        }

                        $selection = $this->selectCandidateOrder($invoice, $dateWindowDays, $amountTolerancePercent);

                        if (($selection['status'] ?? '') === 'none') {
                            $summary['invoices']['no_candidate']++;

                            continue;
                        }

                        if (($selection['status'] ?? '') === 'ambiguous') {
                            $summary['invoices']['ambiguous']++;

                            continue;
                        }

                        /** @var PurchaseOrder|null $order */
                        $order = $selection['order'] ?? null;
                        if (! $order) {
                            $summary['invoices']['no_candidate']++;

                            continue;
                        }

                        $summary['invoices']['eligible']++;

                        if ($dryRun) {
                            continue;
                        }

                        try {
                            $invoice->forceFill([
                                'purchase_order_id' => (int) $order->id,
                                'updated_by' => $actor?->id,
                            ])->save();

                            $summary['invoices']['linked']++;

                            $this->tenantAuditLogger->log(
                                companyId: $currentCompanyId,
                                action: 'tenant.procurement.backfill.vendor_invoice_linked',
                                actor: $actor,
                                description: 'Backfill linked legacy vendor invoice to a purchase order.',
                                entityType: VendorInvoice::class,
                                entityId: (int) $invoice->id,
                                metadata: [
                                    'invoice_number' => (string) $invoice->invoice_number,
                                    'purchase_order_id' => (int) $order->id,
                                    'po_number' => (string) $order->po_number,
                                    'trigger' => 'procurement:backfill-vendor-links',
                                    'company_slug' => (string) ($company?->slug ?? ''),
                                ],
                            );

                            // Control intent: once legacy invoices are linked, immediately recompute match state
                            // so payout gates use current deterministic procurement controls.
                            if ($recomputeMatch) {
                                if (! $actor) {
                                    $summary['invoices']['match_skipped_no_actor']++;
                                } else {
                                    $result = $this->evaluateInvoiceThreeWayMatchService->evaluate(
                                        $actor,
                                        $order->fresh(),
                                        $invoice->fresh()
                                    );

                                    if (in_array((string) $result->match_status, [
                                        InvoiceMatchResult::STATUS_MATCHED,
                                        InvoiceMatchResult::STATUS_OVERRIDDEN,
                                    ], true)) {
                                        $summary['invoices']['match_passed']++;
                                    } else {
                                        $summary['invoices']['match_failed']++;
                                    }
                                }
                            }
                        } catch (\Throwable) {
                            $summary['invoices']['errors']++;
                        }
                    }
                }, 'id');

            if ($syncPayments) {
                $this->syncLegacyPayments(
                    companyId: $currentCompanyId,
                    actor: $actor,
                    dryRun: $dryRun,
                    batchSize: $batchSize,
                    summary: $summary,
                );
            }

            if (! $dryRun) {
                $this->tenantAuditLogger->log(
                    companyId: $currentCompanyId,
                    action: 'tenant.procurement.backfill.vendor_links_run',
                    actor: $actor,
                    description: 'Legacy vendor invoice/payment backfill run completed.',
                    entityType: Company::class,
                    entityId: $currentCompanyId,
                    metadata: [
                        'trigger' => 'procurement:backfill-vendor-links',
                        'settings' => $summary['settings'],
                        'invoice_summary' => $summary['invoices'],
                        'payment_summary' => $summary['payments'],
                    ],
                );
            }
        }

        return $summary;
    }

    /**
     * @return array<int, int>
     */
    private function companyIdsToProcess(?int $companyId): array
    {
        if ($companyId && $companyId > 0) {
            return [$companyId];
        }

        $invoiceCompanyIds = VendorInvoice::query()
            ->select('company_id')
            ->distinct()
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $paymentCompanyIds = VendorInvoicePayment::query()
            ->select('company_id')
            ->distinct()
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $companyIds = array_values(array_unique(array_filter(array_merge($invoiceCompanyIds, $paymentCompanyIds), static fn (int $id): bool => $id > 0)));
        sort($companyIds);

        return $companyIds;
    }

    private function resolveActor(int $companyId): ?User
    {
        $priorityRoles = ['owner', 'finance', 'manager', 'auditor', 'staff'];

        foreach ($priorityRoles as $role) {
            $candidate = User::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('role', $role)
                ->orderBy('id')
                ->first();

            if ($candidate instanceof User) {
                return $candidate;
            }
        }

        $fallback = User::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();

        return $fallback instanceof User ? $fallback : null;
    }

    /**
     * @return array{status:string,order?:PurchaseOrder}
     */
    private function selectCandidateOrder(VendorInvoice $invoice, int $dateWindowDays, float $amountTolerancePercent): array
    {
        $invoiceDate = $invoice->invoice_date
            ? Carbon::parse($invoice->invoice_date)
            : Carbon::parse($invoice->created_at ?? now());

        $referenceText = strtolower(trim(implode(' ', array_filter([
            (string) $invoice->invoice_number,
            (string) ($invoice->description ?? ''),
            (string) ($invoice->notes ?? ''),
        ]))));

        $candidates = PurchaseOrder::query()
            ->where('company_id', (int) $invoice->company_id)
            ->where('vendor_id', (int) $invoice->vendor_id)
            ->whereNotIn('po_status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_CANCELED])
            ->get()
            ->filter(function (PurchaseOrder $order) use ($invoice, $invoiceDate, $dateWindowDays, $amountTolerancePercent): bool {
                $orderDate = $order->issued_at
                    ? Carbon::parse($order->issued_at)
                    : Carbon::parse($order->created_at ?? now());

                $dateDiff = abs($invoiceDate->diffInDays($orderDate, false));
                if ($dateDiff > max(0, $dateWindowDays)) {
                    return false;
                }

                $variancePercent = $this->amountVariancePercent((int) $invoice->total_amount, (int) $order->total_amount);

                return $variancePercent <= max(0.0, $amountTolerancePercent);
            })
            ->values();

        if ($candidates->isEmpty()) {
            return ['status' => 'none'];
        }

        $poNumberMatches = $candidates
            ->filter(function (PurchaseOrder $order) use ($referenceText): bool {
                $poNumber = strtolower(trim((string) ($order->po_number ?? '')));
                if ($poNumber === '') {
                    return false;
                }

                return str_contains($referenceText, $poNumber);
            })
            ->values();

        // Conservative migration rule: link only if there is exactly one clear PO candidate.
        if ($poNumberMatches->count() === 1) {
            return [
                'status' => 'candidate',
                'order' => $poNumberMatches->first(),
            ];
        }

        if ($poNumberMatches->count() > 1) {
            return ['status' => 'ambiguous'];
        }

        if ($candidates->count() === 1) {
            return [
                'status' => 'candidate',
                'order' => $candidates->first(),
            ];
        }

        return ['status' => 'ambiguous'];
    }

    private function amountVariancePercent(int $left, int $right): float
    {
        if ($left <= 0 && $right <= 0) {
            return 0.0;
        }

        $base = max(1, abs($right));

        return round((abs($left - $right) / $base) * 100, 2);
    }

    /**
     * @param  array<string,mixed>  $summary
     */
    private function syncLegacyPayments(int $companyId, ?User $actor, bool $dryRun, int $batchSize, array &$summary): void
    {
        VendorInvoicePayment::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->chunkById(max(1, $batchSize), function ($payments) use (&$summary, $actor, $dryRun): void {
                foreach ($payments as $payment) {
                    if (! $payment instanceof VendorInvoicePayment) {
                        continue;
                    }

                    $summary['payments']['scanned']++;

                    try {
                        $invoice = VendorInvoice::withTrashed()->find((int) $payment->vendor_invoice_id);
                        if (! $invoice instanceof VendorInvoice) {
                            continue;
                        }

                        $companyMismatch = (int) $payment->company_id !== (int) $invoice->company_id;
                        $vendorMismatch = (int) $payment->vendor_id !== (int) $invoice->vendor_id;

                        if (! $companyMismatch && ! $vendorMismatch) {
                            continue;
                        }

                        $summary['payments']['mismatch_found']++;

                        if ($dryRun) {
                            continue;
                        }

                        $payment->forceFill([
                            'company_id' => (int) $invoice->company_id,
                            'vendor_id' => (int) $invoice->vendor_id,
                            'updated_by' => $actor?->id,
                        ])->save();

                        $summary['payments']['synced']++;

                        $this->tenantAuditLogger->log(
                            companyId: (int) $invoice->company_id,
                            action: 'tenant.procurement.backfill.vendor_payment_synced',
                            actor: $actor,
                            description: 'Backfill aligned vendor payment company/vendor to invoice linkage.',
                            entityType: VendorInvoicePayment::class,
                            entityId: (int) $payment->id,
                            metadata: [
                                'vendor_invoice_id' => (int) $invoice->id,
                            ],
                        );
                    } catch (\Throwable) {
                        $summary['payments']['errors']++;
                    }
                }
            }, 'id');
    }
}
