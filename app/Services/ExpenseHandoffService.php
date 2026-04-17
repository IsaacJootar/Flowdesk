<?php

namespace App\Services;

use App\Actions\Expenses\CreateExpense;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\RequestExpenseHandoff;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseHandoffService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly CreateExpense $createExpense,
        private readonly SpendLifecycleControlService $spendLifecycleControlService,
    ) {
    }

    public function prepareForSettledPayout(RequestPayoutExecutionAttempt $attempt, ?int $actorUserId = null): ?RequestExpenseHandoff
    {
        if ((string) $attempt->execution_status !== 'settled') {
            return null;
        }

        $attempt->loadMissing('request.items');
        $request = $attempt->request;
        if (! $request instanceof SpendRequest) {
            return null;
        }

        if ($this->requestHasLinkedExpense($request)) {
            return null;
        }

        $mode = $this->spendLifecycleControlService->expenseHandoffMode((int) $attempt->company_id);
        if ($mode === SpendLifecycleControlService::HANDOFF_MANUAL) {
            $this->activityLogger->log(
                action: 'expense.handoff.manual_trace_gap_logged',
                entityType: SpendRequest::class,
                entityId: (int) $request->id,
                metadata: [
                    'request_code' => (string) $request->request_code,
                    'payout_attempt_id' => (int) $attempt->id,
                    'reason' => 'Payout settled with no linked expense. Tenant setting is manual review.',
                ],
                companyId: (int) $attempt->company_id,
                userId: $actorUserId,
            );

            return null;
        }

        $handoff = $this->pendingHandoffForAttempt($attempt, $request, $mode, $actorUserId);

        if ($mode === SpendLifecycleControlService::HANDOFF_AUTO_CREATE && $actorUserId) {
            $actor = User::query()
                ->where('company_id', (int) $attempt->company_id)
                ->where('is_active', true)
                ->find($actorUserId);

            if ($actor instanceof User && Gate::forUser($actor)->allows('create', Expense::class)) {
                try {
                    $this->createLinkedExpense($handoff, $actor);
                } catch (ValidationException) {
                    $this->activityLogger->log(
                        action: 'expense.handoff.auto_create_failed',
                        entityType: RequestExpenseHandoff::class,
                        entityId: (int) $handoff->id,
                        metadata: [
                            'request_id' => (int) $request->id,
                            'request_code' => (string) $request->request_code,
                            'payout_attempt_id' => (int) $attempt->id,
                        ],
                        companyId: (int) $attempt->company_id,
                        userId: $actorUserId,
                    );
                }
            }
        }

        return $handoff->fresh(['request', 'payoutAttempt', 'expense']) ?? $handoff;
    }

    public function pendingQuery(User $user): Builder
    {
        return RequestExpenseHandoff::query()
            ->where('company_id', (int) $user->company_id)
            ->where('handoff_status', RequestExpenseHandoff::STATUS_PENDING)
            ->with([
                'request.department:id,name',
                'request.vendor:id,name',
                'payoutAttempt:id,company_id,request_id,execution_channel,execution_status,amount,currency_code,provider_reference,settled_at',
            ]);
    }

    /**
     * @throws ValidationException
     */
    public function createLinkedExpense(RequestExpenseHandoff $handoff, User $actor): Expense
    {
        $this->assertResolvable($handoff, $actor);
        $handoff->loadMissing('request.items', 'payoutAttempt');

        $request = $handoff->request;
        $attempt = $handoff->payoutAttempt;
        if (! $request instanceof SpendRequest) {
            throw ValidationException::withMessages([
                'request' => 'The source request is no longer available.',
            ]);
        }

        if ($this->requestHasLinkedExpense($request)) {
            throw ValidationException::withMessages([
                'expense' => 'This request already has a linked expense record.',
            ]);
        }

        $amount = (int) ($attempt?->amount ?: ($request->approved_amount ?: $request->amount));
        $fallbackVendorId = $request->items
            ->first(fn ($item): bool => ! empty($item->vendor_id))
            ?->vendor_id;

        $expense = ($this->createExpense)($actor, [
            'department_id' => (int) $request->department_id,
            'vendor_id' => $request->vendor_id ?: ($fallbackVendorId ? (int) $fallbackVendorId : null),
            'title' => sprintf('%s - %s', (string) $request->request_code, (string) $request->title),
            'description' => $request->description ?: 'Expense created from settled payout.',
            'amount' => $amount,
            'expense_date' => $attempt?->settled_at?->toDateString() ?: now()->toDateString(),
            'payment_method' => $this->paymentMethodFromChannel((string) ($attempt?->execution_channel ?? '')),
            'paid_by_user_id' => (int) $actor->id,
            'is_direct' => false,
            'request_id' => (int) $request->id,
        ]);

        $handoff->forceFill([
            'expense_id' => (int) $expense->id,
            'handoff_status' => RequestExpenseHandoff::STATUS_EXPENSE_CREATED,
            'resolved_by' => (int) $actor->id,
            'resolved_at' => now(),
            'updated_by' => (int) $actor->id,
        ])->save();

        $request->forceFill([
            'paid_amount' => $amount,
            'updated_by' => (int) $actor->id,
        ])->save();

        $this->activityLogger->log(
            action: 'expense.handoff.expense_created',
            entityType: RequestExpenseHandoff::class,
            entityId: (int) $handoff->id,
            metadata: [
                'request_id' => (int) $request->id,
                'request_code' => (string) $request->request_code,
                'expense_id' => (int) $expense->id,
                'expense_code' => (string) $expense->expense_code,
            ],
            companyId: (int) $handoff->company_id,
            userId: (int) $actor->id,
        );

        return $expense;
    }

    /**
     * @throws ValidationException
     */
    public function markNotRequired(RequestExpenseHandoff $handoff, User $actor, string $reason): RequestExpenseHandoff
    {
        $this->assertResolvable($handoff, $actor);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Enter why an expense record is not required.',
            ]);
        }

        $handoff->forceFill([
            'handoff_status' => RequestExpenseHandoff::STATUS_NOT_REQUIRED,
            'resolution_reason' => $reason,
            'resolved_by' => (int) $actor->id,
            'resolved_at' => now(),
            'updated_by' => (int) $actor->id,
        ])->save();

        $this->activityLogger->log(
            action: 'expense.handoff.not_required',
            entityType: RequestExpenseHandoff::class,
            entityId: (int) $handoff->id,
            metadata: [
                'request_id' => (int) $handoff->request_id,
                'reason' => $reason,
            ],
            companyId: (int) $handoff->company_id,
            userId: (int) $actor->id,
        );

        return $handoff->fresh(['request', 'payoutAttempt', 'expense']) ?? $handoff;
    }

    private function pendingHandoffForAttempt(RequestPayoutExecutionAttempt $attempt, SpendRequest $request, string $mode, ?int $actorUserId): RequestExpenseHandoff
    {
        return RequestExpenseHandoff::query()->firstOrCreate(
            [
                'company_id' => (int) $attempt->company_id,
                'request_id' => (int) $request->id,
            ],
            [
                'request_payout_execution_attempt_id' => (int) $attempt->id,
                'handoff_status' => RequestExpenseHandoff::STATUS_PENDING,
                'handoff_mode' => $mode,
                'metadata' => [
                    'request_code' => (string) $request->request_code,
                    'payout_status' => (string) $attempt->execution_status,
                    'amount' => (float) $attempt->amount,
                    'currency_code' => strtoupper((string) ($attempt->currency_code ?: $request->currency ?: 'NGN')),
                ],
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]
        );
    }

    private function assertResolvable(RequestExpenseHandoff $handoff, User $actor): void
    {
        if ((int) $handoff->company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'authorization' => 'This handoff belongs to a different company.',
            ]);
        }

        if ((string) $handoff->handoff_status !== RequestExpenseHandoff::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'This handoff has already been resolved.',
            ]);
        }
    }

    private function requestHasLinkedExpense(SpendRequest $request): bool
    {
        return Expense::query()
            ->where('company_id', (int) $request->company_id)
            ->where('request_id', (int) $request->id)
            ->where('status', '!=', 'void')
            ->exists();
    }

    private function paymentMethodFromChannel(string $channel): ?string
    {
        return match (strtolower(trim($channel))) {
            'bank_transfer', 'transfer' => 'transfer',
            'card' => 'online',
            default => null,
        };
    }
}
