<?php

namespace App\Services\Procurement;

use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RequestCommunicationLogger;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluateInvoiceThreeWayMatchService
{
    public function __construct(
        private readonly ProcurementControlSettingsService $settingsService,
        private readonly TenantAuditLogger $tenantAuditLogger,
        private readonly RequestCommunicationLogger $requestCommunicationLogger,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function evaluate(User $actor, PurchaseOrder $order, VendorInvoice $invoice): InvoiceMatchResult
    {
        if (
            (int) $actor->company_id !== (int) $order->company_id
            || (int) $actor->company_id !== (int) $invoice->company_id
        ) {
            throw ValidationException::withMessages([
                'match' => 'Purchase order or invoice is outside your tenant scope.',
            ]);
        }

        if ((int) $invoice->purchase_order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'match' => 'Invoice must be linked to this purchase order before 3-way match can run.',
            ]);
        }

        $controls = $this->settingsService->effectiveControls((int) $actor->company_id);

        $amountTolerancePercent = max(0, (float) ($controls['match_amount_tolerance_percent'] ?? 0));
        $quantityTolerancePercent = max(0, (float) ($controls['match_quantity_tolerance_percent'] ?? 0));
        $dateToleranceDays = max(0, (int) ($controls['match_date_tolerance_days'] ?? 0));

        /** @var InvoiceMatchResult $result */
        $result = DB::transaction(function () use (
            $actor,
            $order,
            $invoice,
            $amountTolerancePercent,
            $quantityTolerancePercent,
            $dateToleranceDays
        ): InvoiceMatchResult {
            $lockedOrder = PurchaseOrder::query()
                ->with(['items:id,purchase_order_id,quantity,received_quantity', 'receipts:id,purchase_order_id,received_at'])
                ->whereKey((int) $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice = VendorInvoice::query()
                ->whereKey((int) $invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $orderedQuantity = (float) $lockedOrder->items->sum('quantity');
            $receivedQuantity = (float) $lockedOrder->items->sum('received_quantity');
            $poAmount = (int) $lockedOrder->total_amount;
            $invoiceAmount = (int) $lockedInvoice->total_amount;

            $quantityVariancePercent = $this->variancePercent($orderedQuantity, $receivedQuantity);
            $amountVariancePercent = $this->variancePercent((float) $poAmount, (float) $invoiceAmount);
            $dateVarianceDays = $this->invoiceDateVarianceDays($lockedOrder, $lockedInvoice);

            $mismatches = [];

            // Control intent: payment must not proceed when invoice has no receiving evidence.
            if ($receivedQuantity <= 0) {
                $mismatches[] = [
                    'code' => 'no_receipt_recorded',
                    'severity' => InvoiceMatchException::SEVERITY_CRITICAL,
                    'details' => 'No goods receipt has been recorded for this purchase order.',
                    'next_action' => 'Record goods receipt before retrying payment.',
                ];
            }

            if ($quantityVariancePercent > $quantityTolerancePercent) {
                $mismatches[] = [
                    'code' => 'quantity_mismatch',
                    'severity' => InvoiceMatchException::SEVERITY_HIGH,
                    'details' => sprintf(
                        'Ordered qty %.2f vs received qty %.2f exceeds tolerance %.2f%%.',
                        $orderedQuantity,
                        $receivedQuantity,
                        $quantityTolerancePercent
                    ),
                    'next_action' => 'Receive remaining quantity or correct the invoice quantity.',
                ];
            }

            if ($amountVariancePercent > $amountTolerancePercent) {
                $mismatches[] = [
                    'code' => 'amount_mismatch',
                    'severity' => InvoiceMatchException::SEVERITY_HIGH,
                    'details' => sprintf(
                        'PO amount %d vs invoice amount %d exceeds tolerance %.2f%%.',
                        $poAmount,
                        $invoiceAmount,
                        $amountTolerancePercent
                    ),
                    'next_action' => 'Correct invoice amount or revise the PO before payment.',
                ];
            }

            if ($dateVarianceDays > $dateToleranceDays) {
                $mismatches[] = [
                    'code' => 'invoice_date_out_of_window',
                    'severity' => InvoiceMatchException::SEVERITY_MEDIUM,
                    'details' => sprintf(
                        'Invoice date variance %d day(s) exceeds tolerance %d day(s).',
                        $dateVarianceDays,
                        $dateToleranceDays
                    ),
                    'next_action' => 'Review invoice date and supporting delivery evidence.',
                ];
            }

            $status = $mismatches === []
                ? InvoiceMatchResult::STATUS_MATCHED
                : InvoiceMatchResult::STATUS_MISMATCH;

            $score = max(
                0,
                100
                - min(50, (int) round($amountVariancePercent))
                - min(30, (int) round($quantityVariancePercent))
                - min(20, $dateVarianceDays)
            );

            $matchResult = InvoiceMatchResult::query()
                ->firstOrNew([
                    'company_id' => (int) $lockedOrder->company_id,
                    'purchase_order_id' => (int) $lockedOrder->id,
                    'vendor_invoice_id' => (int) $lockedInvoice->id,
                ]);

            $matchResult->forceFill([
                'match_status' => $status,
                'match_score' => $score,
                'mismatch_reason' => $mismatches[0]['code'] ?? null,
                'matched_at' => $status === InvoiceMatchResult::STATUS_MATCHED ? now() : null,
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'metadata' => [
                    'tolerances' => [
                        'amount_percent' => $amountTolerancePercent,
                        'quantity_percent' => $quantityTolerancePercent,
                        'date_days' => $dateToleranceDays,
                    ],
                    'inputs' => [
                        'po_amount' => $poAmount,
                        'invoice_amount' => $invoiceAmount,
                        'ordered_quantity' => $orderedQuantity,
                        'received_quantity' => $receivedQuantity,
                        'date_variance_days' => $dateVarianceDays,
                    ],
                ],
                'updated_by' => (int) $actor->id,
            ]);

            if (! $matchResult->exists) {
                $matchResult->created_by = (int) $actor->id;
            }

            $matchResult->save();

            // Control intent: keep open exceptions aligned to the latest computed mismatch state.
            InvoiceMatchException::query()
                ->where('invoice_match_result_id', (int) $matchResult->id)
                ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                ->delete();

            foreach ($mismatches as $mismatch) {
                InvoiceMatchException::query()->create([
                    'company_id' => (int) $lockedOrder->company_id,
                    'invoice_match_result_id' => (int) $matchResult->id,
                    'purchase_order_id' => (int) $lockedOrder->id,
                    'vendor_invoice_id' => (int) $lockedInvoice->id,
                    'exception_code' => (string) $mismatch['code'],
                    'exception_status' => InvoiceMatchException::STATUS_OPEN,
                    'severity' => (string) $mismatch['severity'],
                    'details' => (string) $mismatch['details'],
                    'metadata' => [
                        'next_action' => (string) $mismatch['next_action'],
                    ],
                    'created_by' => (int) $actor->id,
                    'updated_by' => (int) $actor->id,
                ]);
            }

            return $matchResult->fresh(['exceptions']);
        });

        $isMatched = (string) $result->match_status === InvoiceMatchResult::STATUS_MATCHED;

        $this->tenantAuditLogger->log(
            companyId: (int) $order->company_id,
            action: $isMatched ? 'tenant.procurement.match.passed' : 'tenant.procurement.match.failed',
            actor: $actor,
            description: $isMatched
                ? '3-way match passed and payment gate can proceed.'
                : '3-way match failed and payment gate remains blocked.',
            entityType: InvoiceMatchResult::class,
            entityId: (int) $result->id,
            metadata: [
                'purchase_order_id' => (int) $order->id,
                'vendor_invoice_id' => (int) $invoice->id,
                'match_status' => (string) $result->match_status,
                'match_score' => (float) $result->match_score,
                'open_exception_count' => (int) $result->exceptions
                    ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                    ->count(),
            ],
        );

        // Notify finance/owner roles when a match fails — they are the ones who must act to unblock payment.
        if (! $isMatched) {
            $spendRequest = $order->spendRequest()->withoutGlobalScopes()->first();
            if ($spendRequest) {
                $communicationSettings = CompanyCommunicationSetting::query()
                    ->firstOrCreate(
                        ['company_id' => (int) $order->company_id],
                        CompanyCommunicationSetting::defaultAttributes()
                    );

                $financeOwnerIds = User::query()
                    ->where('company_id', (int) $order->company_id)
                    ->whereIn('role', [UserRole::Owner->value, UserRole::Finance->value])
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $openExceptionCount = (int) $result->exceptions
                    ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                    ->count();

                if ($financeOwnerIds !== []) {
                    $this->requestCommunicationLogger->log(
                        request: $spendRequest,
                        event: 'request.invoice.mismatch',
                        channels: $communicationSettings->selectableChannels() ?: ['in_app'],
                        recipientUserIds: $financeOwnerIds,
                        requestApprovalId: null,
                        metadata: [
                            'request_code' => (string) $spendRequest->request_code,
                            'po_number' => (string) $order->po_number,
                            'match_score' => (float) $result->match_score,
                            'open_exception_count' => $openExceptionCount,
                        ],
                    );
                }
            }
        }

        return $result;
    }

    private function variancePercent(float $left, float $right): float
    {
        if ($left <= 0 && $right <= 0) {
            return 0;
        }

        $base = max(1, abs($left));

        return round((abs($left - $right) / $base) * 100, 2);
    }

    private function invoiceDateVarianceDays(PurchaseOrder $order, VendorInvoice $invoice): int
    {
        $invoiceDate = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : null;
        if (! $invoiceDate) {
            return 0;
        }

        $referenceDate = $order->receipts->max('received_at') ?: $order->issued_at ?: $order->created_at;
        if (! $referenceDate) {
            return 0;
        }

        return abs($invoiceDate->diffInDays(Carbon::parse($referenceDate), false));
    }
}