<?php

namespace App\Services;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\RequestExpenseHandoff;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use Throwable;

class ExpenseHandoffBackfillService
{
    public function __construct(
        private readonly ExpenseHandoffService $expenseHandoffService,
        private readonly SpendLifecycleControlService $spendLifecycleControlService,
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   company_scope: int|null,
     *   scanned: int,
     *   eligible: int,
     *   created: int,
     *   already_has_expense: int,
     *   already_has_handoff: int,
     *   manual_mode_skipped: int,
     *   missing_request: int,
     *   errors: int
     * }
     */
    public function run(?int $companyId = null, bool $dryRun = true, int $batchSize = 200): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'company_scope' => $companyId,
            'scanned' => 0,
            'eligible' => 0,
            'created' => 0,
            'already_has_expense' => 0,
            'already_has_handoff' => 0,
            'manual_mode_skipped' => 0,
            'missing_request' => 0,
            'errors' => 0,
        ];

        RequestPayoutExecutionAttempt::query()
            ->where('execution_status', 'settled')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->with(['request'])
            ->orderBy('id')
            ->chunkById(max(1, $batchSize), function ($attempts) use (&$summary, $dryRun): void {
                foreach ($attempts as $attempt) {
                    $summary['scanned']++;

                    try {
                        $request = $attempt->request;
                        if (! $request instanceof SpendRequest) {
                            $summary['missing_request']++;
                            continue;
                        }

                        if ($this->hasLinkedExpense($request)) {
                            $summary['already_has_expense']++;
                            continue;
                        }

                        if ($this->hasHandoff($request)) {
                            $summary['already_has_handoff']++;
                            continue;
                        }

                        $mode = $this->spendLifecycleControlService->expenseHandoffMode((int) $attempt->company_id);
                        if ($mode === SpendLifecycleControlService::HANDOFF_MANUAL) {
                            $summary['manual_mode_skipped']++;
                            continue;
                        }

                        $summary['eligible']++;

                        if ($dryRun) {
                            continue;
                        }

                        $handoff = $this->expenseHandoffService->prepareForSettledPayout(
                            attempt: $attempt,
                            actorUserId: $attempt->updated_by ? (int) $attempt->updated_by : ((int) $attempt->created_by ?: null),
                        );

                        if ($handoff instanceof RequestExpenseHandoff) {
                            $summary['created']++;
                        }
                    } catch (Throwable) {
                        $summary['errors']++;
                    }
                }
            });

        return $summary;
    }

    private function hasLinkedExpense(SpendRequest $request): bool
    {
        return Expense::query()
            ->where('company_id', (int) $request->company_id)
            ->where('request_id', (int) $request->id)
            ->where('status', '!=', 'void')
            ->exists();
    }

    private function hasHandoff(SpendRequest $request): bool
    {
        return RequestExpenseHandoff::query()
            ->where('company_id', (int) $request->company_id)
            ->where('request_id', (int) $request->id)
            ->exists();
    }
}
