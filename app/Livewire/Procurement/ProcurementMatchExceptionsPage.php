<?php

namespace App\Livewire\Procurement;

use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Procurement\ProcurementControlSettingsService;
use App\Services\TenantAuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProcurementMatchExceptionsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'open';

    public string $severityFilter = 'all';

    public int $perPage = 10;

    public ?int $selectedExceptionId = null;

    public string $resolutionNotes = '';

    public bool $showResolutionModal = false;

    public string $resolutionAction = 'resolved';

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
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function openResolutionModal(int $exceptionId, string $action): void
    {
        if (! in_array($action, ['resolved', 'waived'], true)) {
            return;
        }

        $this->selectedExceptionId = $exceptionId;
        $this->resolutionAction = $action;
        $this->resolutionNotes = '';
        $this->showResolutionModal = true;
    }

    public function closeResolutionModal(): void
    {
        $this->showResolutionModal = false;
        $this->selectedExceptionId = null;
        $this->resolutionNotes = '';
        $this->resolutionAction = 'resolved';
    }

    /**
     * @throws ValidationException
     */
    public function applyResolution(TenantAuditLogger $tenantAuditLogger, ProcurementControlSettingsService $settingsService): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        if (! $this->selectedExceptionId) {
            $this->setFeedbackError('Select an exception to resolve first.');

            return;
        }

        $validated = $this->validate([
            'resolutionNotes' => ['required', 'string', 'max:2000'],
        ]);

        $controls = $settingsService->effectiveControls((int) $user->company_id);
        $allowedRoles = (array) ($controls['match_override_allowed_roles'] ?? ['owner', 'finance']);
        if (! in_array(strtolower((string) $user->role), $allowedRoles, true)) {
            $this->setFeedbackError(sprintf('Only [%s] can resolve procurement match exceptions.', implode(', ', $allowedRoles)));

            return;
        }

        $exception = InvoiceMatchException::query()
            ->with('matchResult')
            ->whereKey((int) $this->selectedExceptionId)
            ->firstOrFail();

        if ((int) $exception->company_id !== (int) $user->company_id) {
            $this->setFeedbackError('This exception does not belong to your tenant scope.');

            return;
        }

        if ((string) $exception->exception_status !== InvoiceMatchException::STATUS_OPEN) {
            $this->setFeedbackError('This exception is already closed.');

            return;
        }

        $newStatus = $this->resolutionAction === 'waived'
            ? InvoiceMatchException::STATUS_WAIVED
            : InvoiceMatchException::STATUS_RESOLVED;

        $exception->forceFill([
            'exception_status' => $newStatus,
            'resolution_notes' => (string) $validated['resolutionNotes'],
            'resolved_at' => now(),
            'resolved_by_user_id' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ])->save();

        $matchResult = $exception->matchResult;

        if ($matchResult instanceof InvoiceMatchResult) {
            $openCount = InvoiceMatchException::query()
                ->where('invoice_match_result_id', (int) $matchResult->id)
                ->where('exception_status', InvoiceMatchException::STATUS_OPEN)
                ->count();

            // Control intent: manual closure of all open exceptions explicitly marks result as overridden.
            if ($openCount === 0) {
                $matchResult->forceFill([
                    'match_status' => InvoiceMatchResult::STATUS_OVERRIDDEN,
                    'resolved_at' => now(),
                    'resolved_by_user_id' => (int) $user->id,
                    'updated_by' => (int) $user->id,
                ])->save();
            }
        }

        $tenantAuditLogger->log(
            companyId: (int) $user->company_id,
            action: $newStatus === InvoiceMatchException::STATUS_WAIVED
                ? 'tenant.procurement.match.exception.waived'
                : 'tenant.procurement.match.exception.resolved',
            actor: $user,
            description: 'Procurement match exception updated from the exceptions workbench.',
            entityType: InvoiceMatchException::class,
            entityId: (int) $exception->id,
            metadata: [
                'invoice_match_result_id' => (int) $exception->invoice_match_result_id,
                'purchase_order_id' => (int) ($exception->purchase_order_id ?? 0),
                'vendor_invoice_id' => (int) ($exception->vendor_invoice_id ?? 0),
                'exception_code' => (string) $exception->exception_code,
                'new_status' => $newStatus,
            ],
        );

        $this->closeResolutionModal();
        $this->setFeedback('Procurement match exception updated.');
    }

    public function render(): View
    {
        $query = InvoiceMatchException::query()
            ->with([
                'order:id,po_number',
                'invoice:id,invoice_number,total_amount,currency',
                'matchResult:id,match_status,match_score',
            ])
            ->when($this->search !== '', function ($builder): void {
                $builder->where(function ($inner): void {
                    $inner->where('exception_code', 'like', '%'.$this->search.'%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('po_number', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('exception_status', $this->statusFilter))
            ->when($this->severityFilter !== 'all', fn ($builder) => $builder->where('severity', $this->severityFilter))
            ->latest('id');

        $exceptions = $this->readyToLoad
            ? (clone $query)->paginate($this->perPage)
            : InvoiceMatchException::query()->whereRaw('1=0')->paginate($this->perPage);

        $summary = $this->readyToLoad
            ? [
                'open' => (clone $query)->where('exception_status', InvoiceMatchException::STATUS_OPEN)->count(),
                'resolved' => (clone $query)->whereIn('exception_status', [InvoiceMatchException::STATUS_RESOLVED, InvoiceMatchException::STATUS_WAIVED])->count(),
                'high' => (clone $query)->whereIn('severity', [InvoiceMatchException::SEVERITY_HIGH, InvoiceMatchException::SEVERITY_CRITICAL])->count(),
            ]
            : ['open' => 0, 'resolved' => 0, 'high' => 0];

        return view('livewire.procurement.procurement-match-exceptions-page', [
            'exceptions' => $exceptions,
            'summary' => $summary,
            'statuses' => ['all', InvoiceMatchException::STATUS_OPEN, InvoiceMatchException::STATUS_RESOLVED, InvoiceMatchException::STATUS_WAIVED],
            'severities' => ['all', InvoiceMatchException::SEVERITY_LOW, InvoiceMatchException::SEVERITY_MEDIUM, InvoiceMatchException::SEVERITY_HIGH, InvoiceMatchException::SEVERITY_CRITICAL],
        ]);
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

    private function canAccessPage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }
}