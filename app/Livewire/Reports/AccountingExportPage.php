<?php

namespace App\Livewire\Reports;

use App\Actions\Accounting\ExportAccountingCsv;
use App\Domains\Accounting\Models\AccountingExportBatch;
use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Enums\AccountingSyncStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Accounting Export')]
class AccountingExportPage extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public bool $canExport = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $this->canExport = $this->canManage($user);
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function exportCsv(ExportAccountingCsv $exportAccountingCsv): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        if (! $this->canManage($user)) {
            throw new AuthorizationException('Only owner and finance can export accounting records.');
        }

        $this->feedbackError = null;
        $this->feedbackMessage = null;

        try {
            $batch = $exportAccountingCsv($user, [
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
            ]);

            $this->setFeedback('Accounting CSV export is ready with '.$batch->row_count.' row(s).');
        } catch (ValidationException $exception) {
            $this->setFeedbackError((string) collect($exception->errors())->flatten()->first());
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $companyId = (int) $user->company_id;
        $from = $this->safeDate($this->fromDate, now()->startOfMonth()->toDateString());
        $to = $this->safeDate($this->toDate, now()->toDateString());

        $base = AccountingSyncEvent::query()
            ->where('company_id', $companyId)
            ->where('provider', 'csv')
            ->whereDate('event_date', '>=', $from)
            ->whereDate('event_date', '<=', $to);

        $summary = [
            'ready' => (clone $base)->where('status', AccountingSyncStatus::Pending->value)->count(),
            'needs_mapping' => (clone $base)->where('status', AccountingSyncStatus::NeedsMapping->value)->count(),
            'exported' => (clone $base)->where('status', AccountingSyncStatus::Exported->value)->count(),
            'skipped' => (clone $base)->where('status', AccountingSyncStatus::Skipped->value)->count(),
        ];

        $missingRows = (clone $base)
            ->where('status', AccountingSyncStatus::NeedsMapping->value)
            ->orderBy('event_date')
            ->limit(8)
            ->get();

        $readyRows = (clone $base)
            ->where('status', AccountingSyncStatus::Pending->value)
            ->orderBy('event_date')
            ->orderBy('id')
            ->limit(10)
            ->get();

        $batches = AccountingExportBatch::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('livewire.reports.accounting-export-page', [
            'summary' => $summary,
            'missingRows' => $missingRows,
            'readyRows' => $readyRows,
            'batches' => $batches,
        ]);
    }

    private function canView(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }

    private function safeDate(string $value, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : $fallback;
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
