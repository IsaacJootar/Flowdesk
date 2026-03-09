<?php

namespace App\Services\AI;

use App\Domains\Requests\Models\SpendRequest;
use Illuminate\Support\Carbon;

class RequestFlowAgentService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     summary:string,
     *     items:array<int, array{
     *         severity:string,
     *         title:string,
     *         message:string,
     *         action_key:string|null,
     *         action_label:string|null
     *     }>,
     *     generated_at:string
     * }
     */
    public function analyzeDraft(array $payload): array
    {
        $items = [];

        $title = trim((string) ($payload['title'] ?? ''));
        $workflowId = (int) ($payload['workflow_id'] ?? 0);
        $requiresAmount = (bool) ($payload['requires_amount'] ?? false);
        $requiresLineItems = (bool) ($payload['requires_line_items'] ?? false);
        $requiresVendor = (bool) ($payload['requires_vendor'] ?? false);
        $requiresAttachments = (bool) ($payload['requires_attachments'] ?? false);
        $amount = (int) ($payload['amount'] ?? 0);
        $lineItems = array_values((array) ($payload['line_items'] ?? []));
        $attachmentsCount = (int) ($payload['attachments_count'] ?? 0);
        $neededBy = trim((string) ($payload['needed_by'] ?? ''));

        if ($title === '') {
            $items[] = $this->item(
                'action',
                'Missing Title',
                'Add a clear request title so approvers understand the business purpose quickly.'
            );
        }

        if ($workflowId <= 0) {
            $items[] = $this->item(
                'action',
                'Workflow Not Selected',
                'Choose an approval workflow before submission to avoid routing errors.'
            );
        }

        if ($requiresAmount && ! $requiresLineItems && $amount <= 0) {
            $items[] = $this->item(
                'action',
                'Amount Required',
                'Set a valid request amount greater than zero before submitting this draft.'
            );
        }

        if ($requiresLineItems) {
            $validLines = collect($lineItems)->filter(function (array $line): bool {
                $quantity = (int) ($line['quantity'] ?? 0);
                $unitCost = (int) ($line['unit_cost'] ?? 0);

                return $quantity > 0 && $unitCost > 0;
            })->count();

            if ($validLines === 0) {
                $items[] = $this->item(
                    'action',
                    'Line Items Incomplete',
                    'Add at least one line item with quantity and unit cost before submission.'
                );
            }

            if ($requiresVendor) {
                $missingVendorCount = collect($lineItems)->filter(function (array $line): bool {
                    return (int) ($line['quantity'] ?? 0) > 0
                        && (int) ($line['unit_cost'] ?? 0) > 0
                        && (int) ($line['vendor_id'] ?? 0) <= 0;
                })->count();

                if ($missingVendorCount > 0) {
                    $items[] = $this->item(
                        'watch',
                        'Vendor Link Recommended',
                        'Some priced line items do not have a vendor. Link vendors to reduce procurement handoff delays.'
                    );
                }
            }
        }

        if ($requiresAttachments && $attachmentsCount <= 0) {
            $items[] = $this->item(
                'watch',
                'No Supporting Files',
                'This request type usually needs documents. Attach evidence to reduce return cycles.'
            );
        }

        if ($neededBy !== '') {
            try {
                $neededByDate = Carbon::parse($neededBy)->startOfDay();
                if ($neededByDate->isPast()) {
                    $items[] = $this->item(
                        'watch',
                        'Needed-By Date In The Past',
                        'Update the needed-by date so execution planning does not start with overdue timing.'
                    );
                }
            } catch (\Throwable) {
                // Skip date-specific recommendation when input cannot be parsed.
            }
        }

        if ($items === []) {
            $items[] = $this->item(
                'ok',
                'Draft Looks Ready',
                'Key checks passed. You can continue editing or submit for approval.'
            );
        }

        return [
            'summary' => $this->summary($items),
            'items' => $items,
            'generated_at' => now()->format('M d, Y H:i'),
        ];
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array{
     *     summary:string,
     *     items:array<int, array{
     *         severity:string,
     *         title:string,
     *         message:string,
     *         action_key:string|null,
     *         action_label:string|null
     *     }>,
     *     generated_at:string
     * }
     */
    public function analyzeRequest(SpendRequest $request, array $capabilities = []): array
    {
        $request->loadMissing(['attachments:id,request_id', 'expenses:id,request_id', 'purchaseOrders:id,spend_request_id']);

        $items = [];
        $status = (string) $request->status;
        $metadata = (array) ($request->metadata ?? []);
        $policyWarnings = array_values(array_filter(array_map('strval', (array) ($metadata['policy_warnings'] ?? []))));

        if (in_array($status, ['failed', 'reversed'], true)) {
            $items[] = $this->item(
                'action',
                'Execution Incident Detected',
                'Review execution logs and reconciliation events before retrying this request.'
            );
        } elseif ($status === 'returned') {
            $items[] = $this->item(
                'action',
                'Returned For Changes',
                'Address reviewer comments and policy flags, then resubmit for approval.'
            );
        }

        if ($status === 'approved'
            && $request->expenses->isEmpty()
            && $request->purchaseOrders->isEmpty()
        ) {
            $items[] = $this->item(
                'watch',
                'Approved But Not Handed Off',
                'Consider creating an Expense or converting to PO so downstream execution can proceed.'
            );
        }

        if (in_array($status, ['approved', 'approved_for_execution'], true) && (bool) ($capabilities['can_convert_to_po'] ?? false)) {
            $items[] = $this->item(
                'action',
                'Convert To Procurement Order',
                'Flow Agent can convert this request to a purchase order now.',
                'convert_to_po',
                'Run Flow Agent Convert'
            );
        }

        if ($status === 'approved' && (bool) ($capabilities['can_create_expense'] ?? false)) {
            $items[] = $this->item(
                'action',
                'Create Expense Record',
                'Flow Agent can create the linked expense record now.',
                'create_expense',
                'Run Flow Agent Expense'
            );
        }

        if (in_array($status, ['draft', 'returned'], true) && $request->attachments->isEmpty()) {
            $items[] = $this->item(
                'watch',
                'No Attachments',
                'Attach supporting files to reduce approval turnaround and avoid avoidable returns.'
            );
        }

        foreach ($policyWarnings as $warning) {
            $items[] = $this->item(
                'watch',
                'Policy Warning',
                $warning
            );
        }

        if ($status === 'in_review' && (int) ($request->current_approval_step ?? 0) > 0) {
            $items[] = $this->item(
                'ok',
                'Active Approval Step',
                'Request is currently in review. Monitor timeline and communication history for next actions.'
            );
        }

        if ($items === []) {
            $items[] = $this->item(
                'ok',
                'No Immediate Risk Signals',
                'No high-priority intervention is currently suggested for this request.'
            );
        }

        return [
            'summary' => $this->summary($items),
            'items' => $items,
            'generated_at' => now()->format('M d, Y H:i'),
        ];
    }

    /**
     * @return array{severity:string,title:string,message:string,action_key:string|null,action_label:string|null}
     */
    private function item(
        string $severity,
        string $title,
        string $message,
        ?string $actionKey = null,
        ?string $actionLabel = null
    ): array {
        return [
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_key' => $actionKey,
            'action_label' => $actionLabel,
        ];
    }

    /**
     * @param  array<int, array{
     *     severity:string,
     *     title:string,
     *     message:string,
     *     action_key:string|null,
     *     action_label:string|null
     * }>  $items
     */
    private function summary(array $items): string
    {
        $action = collect($items)->where('severity', 'action')->count();
        $watch = collect($items)->where('severity', 'watch')->count();
        $ok = collect($items)->where('severity', 'ok')->count();

        if ($action > 0) {
            return sprintf(
                '%d action item(s) and %d watch item(s) identified. Resolve action items first.',
                $action,
                $watch
            );
        }

        if ($watch > 0) {
            return sprintf('%d watch item(s) identified. Review before final action.', $watch);
        }

        return $ok > 0
            ? 'No blocking risk signals found. Continue with normal workflow decisions.'
            : 'No signals generated.';
    }
}
