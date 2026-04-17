<?php

namespace App\Livewire\Operations;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\RequestExpenseHandoff;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ExpenseHandoffService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Expense Handoff')]
class ExpenseHandoffPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    /**
     * @var array<int, string>
     */
    public array $notRequiredReasons = [];

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 120);
        $this->resetPage();
    }

    public function createExpense(int $handoffId, ExpenseHandoffService $expenseHandoffService): void
    {
        $this->feedbackError = null;
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $handoff = $this->findHandoff($handoffId, $user);

        try {
            $expense = $expenseHandoffService->createLinkedExpense($handoff, $user);
        } catch (ValidationException $exception) {
            $this->setFeedbackError((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->setFeedback('Linked expense '.$expense->expense_code.' created.');
    }

    public function markNotRequired(int $handoffId, ExpenseHandoffService $expenseHandoffService): void
    {
        $this->feedbackError = null;
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $handoff = $this->findHandoff($handoffId, $user);

        try {
            $expenseHandoffService->markNotRequired(
                handoff: $handoff,
                actor: $user,
                reason: (string) ($this->notRequiredReasons[$handoffId] ?? '')
            );
        } catch (ValidationException $exception) {
            $this->setFeedbackError((string) collect($exception->errors())->flatten()->first());

            return;
        }

        unset($this->notRequiredReasons[$handoffId]);
        $this->setFeedback('Handoff marked not required.');
    }

    public function render(ExpenseHandoffService $expenseHandoffService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $query = $expenseHandoffService->pendingQuery($user)
            ->latest('id');

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('request', function ($requestQuery) use ($search): void {
                    $requestQuery
                        ->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%');
                });
            });
        }

        $handoffs = $this->readyToLoad
            ? $query->paginate(10)
            : RequestExpenseHandoff::query()->whereRaw('1 = 0')->paginate(10);

        return view('livewire.operations.expense-handoff-page', [
            'handoffs' => $handoffs,
            'rows' => $this->rows($handoffs->getCollection()),
            'canCreateExpense' => Gate::allows('create', Expense::class),
        ]);
    }

    private function findHandoff(int $handoffId, User $user): RequestExpenseHandoff
    {
        return RequestExpenseHandoff::query()
            ->where('company_id', (int) $user->company_id)
            ->where('handoff_status', RequestExpenseHandoff::STATUS_PENDING)
            ->with(['request.items', 'request.department:id,name', 'request.vendor:id,name', 'payoutAttempt'])
            ->findOrFail($handoffId);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RequestExpenseHandoff>  $handoffs
     * @return array<int, array<string,mixed>>
     */
    private function rows(\Illuminate\Support\Collection $handoffs): array
    {
        return $handoffs->map(function (RequestExpenseHandoff $handoff): array {
            $request = $handoff->request;
            $attempt = $handoff->payoutAttempt;
            $currency = strtoupper((string) ($attempt?->currency_code ?: $request?->currency ?: 'NGN'));

            return [
                'id' => (int) $handoff->id,
                'request_code' => (string) ($request?->request_code ?? 'Unknown request'),
                'title' => (string) ($request?->title ?? ''),
                'department' => (string) ($request?->department?->name ?? '-'),
                'vendor' => (string) ($request?->vendor?->name ?? 'Unlinked'),
                'amount' => Money::formatCurrency((int) ($attempt?->amount ?: $request?->approved_amount ?: $request?->amount ?: 0), $currency),
                'payment_method' => $this->label((string) ($attempt?->execution_channel ?? '')),
                'payment_status' => $this->label((string) ($attempt?->execution_status ?? '')),
                'provider_reference' => (string) ($attempt?->provider_reference ?? ''),
                'settled_at' => $attempt?->settled_at?->format('M j, Y g:ia') ?? '-',
                'request_url' => $request ? route('requests.index', ['open_request_id' => (int) $request->id]) : route('requests.index'),
                'trace_url' => route('reports.financial-trace', ['search' => (string) ($request?->request_code ?? '')]),
            ];
        })->values()->all();
    }

    private function canAccessPage(User $user): bool
    {
        return (bool) $user->is_active
            && (int) $user->company_id > 0
            && in_array((string) $user->role, [
                UserRole::Owner->value,
                UserRole::Finance->value,
                UserRole::Auditor->value,
            ], true);
    }

    private function label(string $value): string
    {
        $value = trim($value);

        return $value === '' ? '-' : ucwords(str_replace('_', ' ', $value));
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }
}
