<?php

namespace App\Livewire\Requests;

use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Enums\UserRole;
use App\Services\RequestCommunicationRetryService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Request Communications')]
class RequestCommunicationsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $activeTab = 'inbox';

    public string $search = '';

    public string $channelFilter = 'all';

    public string $statusFilter = 'all';

    public int $perPage = 10;

    /**
     * Keep this untyped so Livewire can hold temporary empty-string input while user edits.
     */
    public $queuedOlderThanMinutes = 2;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', SpendRequest::class), 403);
    }

    public function retryLog(int $logId, RequestCommunicationRetryService $retryService): void
    {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');
            return;
        }

        $log = RequestCommunicationLog::query()->find($logId);
        if (! $log) {
            $this->setFeedbackError('Communication record not found.');

            return;
        }

        if (! in_array((string) $log->status, ['failed', 'queued', 'skipped'], true)) {
            $this->setFeedbackError('Only failed, skipped, or queued records can be retried.');

            return;
        }

        $after = $retryService->retryLog($log);
        $status = ucfirst((string) $after->status);
        $this->setFeedback("Retry completed. Current status: {$status}.");
    }

    public function retryFailed(RequestCommunicationRetryService $retryService): void
    {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');
            return;
        }

        $stats = $retryService->retryFailed($this->companyId(), 300);
        $this->setFeedback(
            "Retry failed done. Retried {$stats['retried']}, sent {$stats['sent']}, failed {$stats['failed']}, skipped {$stats['skipped']}."
        );
    }

    public function processQueuedBacklog(RequestCommunicationRetryService $retryService): void
    {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');
            return;
        }

        $olderThan = max(0, (int) $this->queuedOlderThanMinutes);
        $stats = $retryService->processStuckQueued($this->companyId(), $olderThan, 500);
        $this->setFeedback(
            "Queued processing done. Processed {$stats['processed']}, sent {$stats['sent']}, failed {$stats['failed']}, remaining queued {$stats['remaining_queued']}."
        );
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedChannelFilter(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedQueuedOlderThanMinutes(mixed $value): void
    {
        // Allow in-progress typing (empty input) without throwing hydration/type errors.
        if ($value === null || $value === '') {
            return;
        }

        $this->queuedOlderThanMinutes = max(0, (int) $value);
        $this->resetPage(pageName: $this->pageName());
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'delivery'], true)) {
            return;
        }

        // Enforce least privilege even if UI is manipulated client-side.
        if ($tab === 'delivery' && ! $this->canViewDeliveryLogs()) {
            $this->setFeedbackError('You are not allowed to view delivery logs.');
            $this->activeTab = 'inbox';
            $this->resetPage(pageName: $this->pageName());

            return;
        }

        $this->activeTab = $tab;
        $this->resetPage(pageName: $this->pageName());
    }

    public function markRead(int $logId): void
    {
        $this->markInboxMessageAsRead('requests', $logId);
    }

    public function markVendorRead(int $logId): void
    {
        $this->markInboxMessageAsRead('vendors', $logId);
    }

    public function markReadBySource(string $source, int $logId): void
    {
        $normalized = strtolower(trim($source));
        if (! in_array($normalized, ['requests', 'vendors'], true)) {
            $this->setFeedbackError('Notification source is invalid.');

            return;
        }

        $this->markInboxMessageAsRead($normalized, $logId);
    }

    public function markAllRead(): void
    {
        $userId = (int) \Illuminate\Support\Facades\Auth::id();

        // Inbox read-state spans all in-app internal notifications, regardless of module.
        RequestCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        VendorCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->setFeedback('All inbox notifications marked as read.');
    }

    public function render(): View
    {
        $canViewDeliveryLogs = $this->canViewDeliveryLogs();
        if ($this->activeTab === 'delivery' && ! $canViewDeliveryLogs) {
            $this->activeTab = 'inbox';
        }

        $requestInboxUnreadCount = RequestCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) \Illuminate\Support\Facades\Auth::id())
            ->whereNull('read_at')
            ->count();
        $vendorInboxUnreadCount = VendorCommunicationLog::query()
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) \Illuminate\Support\Facades\Auth::id())
            ->whereNull('read_at')
            ->count();
        $inboxUnreadCount = $requestInboxUnreadCount + $vendorInboxUnreadCount;
        $canManageDeliveryOps = $this->canExecuteDeliveryOps();
        $deliverySummary = ['failed' => 0, 'queued' => 0];
        if ($canViewDeliveryLogs) {
            $deliverySummary['failed'] = RequestCommunicationLog::query()
                ->where('status', 'failed')
                ->count();
            $deliverySummary['queued'] = RequestCommunicationLog::query()
                ->where('status', 'queued')
                ->where('created_at', '<=', now()->subMinutes(max(0, (int) $this->queuedOlderThanMinutes)))
                ->count();
        }

        if (! $this->readyToLoad) {
            $communications = $this->emptyPaginator();
        } elseif ($this->activeTab === 'inbox') {
            $communications = $this->inboxMessagesQuery()
                ->paginate($this->perPage, pageName: $this->pageName());
        } else {
            $communications = $this->communicationsQuery()->paginate($this->perPage, pageName: $this->pageName());
        }

        return view('livewire.requests.request-communications-page', [
            'communications' => $communications,
            'inboxUnreadCount' => $inboxUnreadCount,
            'channels' => ['in_app', 'email', 'sms'],
            'statuses' => ['queued', 'sent', 'failed', 'skipped'],
            'canViewDeliveryLogs' => $canViewDeliveryLogs,
            'canManageDeliveryOps' => $canManageDeliveryOps,
            'deliverySummary' => $deliverySummary,
        ]);
    }

    private function communicationsQuery(): Builder
    {
        $query = RequestCommunicationLog::query()
            ->with([
                'request:id,request_code,title,requested_by',
                'recipient:id,name',
            ]);

        // One component serves two datasets: personal inbox vs operational delivery logs.
        if ($this->activeTab === 'inbox') {
            $query->where('channel', 'in_app')
                ->where('recipient_user_id', (int) \Illuminate\Support\Facades\Auth::id());
        } else {
            if (! $this->canViewDeliveryLogs()) {
                $query->whereRaw('1 = 0');

                return $query->latest('id');
            }
            $this->applyDeliveryAccessScope($query);
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('event', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%')
                    ->orWhereHas('request', fn (Builder $requestQuery) => $requestQuery
                        ->where('request_code', 'like', '%'.$search.'%')
                        ->orWhere('title', 'like', '%'.$search.'%'))
                    ->orWhereHas('recipient', fn (Builder $recipientQuery) => $recipientQuery
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($this->channelFilter !== 'all') {
            $query->where('channel', $this->channelFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest('id');
    }

    private function inboxMessagesQuery(): \Illuminate\Database\Query\Builder
    {
        $userId = (int) \Illuminate\Support\Facades\Auth::id();

        // Request-origin in-app notifications
        $requestInbox = DB::table('request_communication_logs')
            ->select([
                DB::raw("'requests' as source_section"),
                'request_communication_logs.id',
                'request_communication_logs.event',
                'request_communication_logs.channel',
                'request_communication_logs.status',
                'request_communication_logs.message',
                'request_communication_logs.read_at',
                'request_communication_logs.created_at',
                'request_communication_logs.recipient_user_id',
                'request_communication_logs.request_id',
                DB::raw('NULL as vendor_id'),
                DB::raw('NULL as vendor_invoice_id'),
            ])
            ->where('request_communication_logs.channel', 'in_app')
            ->where('request_communication_logs.recipient_user_id', $userId);

        // Vendor/payables-origin in-app notifications
        $vendorInbox = DB::table('vendor_communication_logs')
            ->select([
                DB::raw("'vendors' as source_section"),
                'vendor_communication_logs.id',
                'vendor_communication_logs.event',
                'vendor_communication_logs.channel',
                'vendor_communication_logs.status',
                'vendor_communication_logs.message',
                'vendor_communication_logs.read_at',
                'vendor_communication_logs.created_at',
                'vendor_communication_logs.recipient_user_id',
                DB::raw('NULL as request_id'),
                'vendor_communication_logs.vendor_id',
                'vendor_communication_logs.vendor_invoice_id',
            ])
            ->where('vendor_communication_logs.channel', 'in_app')
            ->where('vendor_communication_logs.recipient_user_id', $userId);

        $combined = $requestInbox->unionAll($vendorInbox);

        $query = DB::query()
            ->fromSub($combined, 'messages')
            ->leftJoin('requests as requests', 'requests.id', '=', 'messages.request_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'messages.vendor_id')
            ->leftJoin('vendor_invoices as invoices', 'invoices.id', '=', 'messages.vendor_invoice_id')
            ->leftJoin('users as recipients', 'recipients.id', '=', 'messages.recipient_user_id')
            ->select([
                'messages.source_section',
                'messages.id',
                'messages.event',
                'messages.channel',
                'messages.status',
                'messages.message',
                'messages.read_at',
                'messages.created_at',
                'messages.recipient_user_id',
                'messages.request_id',
                'messages.vendor_id',
                'messages.vendor_invoice_id',
                'requests.request_code as request_code',
                'requests.title as request_title',
                'vendors.name as vendor_name',
                'invoices.invoice_number as invoice_number',
                'recipients.name as recipient_name',
            ]);

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function (\Illuminate\Database\Query\Builder $builder) use ($search): void {
                $builder
                    ->where('messages.event', 'like', '%'.$search.'%')
                    ->orWhere('messages.message', 'like', '%'.$search.'%')
                    ->orWhere('requests.request_code', 'like', '%'.$search.'%')
                    ->orWhere('requests.title', 'like', '%'.$search.'%')
                    ->orWhere('vendors.name', 'like', '%'.$search.'%')
                    ->orWhere('invoices.invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('recipients.name', 'like', '%'.$search.'%');
            });
        }

        if ($this->channelFilter !== 'all') {
            $query->where('messages.channel', $this->channelFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('messages.status', $this->statusFilter);
        }

        return $query->orderByDesc('messages.created_at')->orderByDesc('messages.id');
    }

    private function markInboxMessageAsRead(string $source, int $logId): void
    {
        $userId = (int) \Illuminate\Support\Facades\Auth::id();

        $log = match ($source) {
            'vendors' => VendorCommunicationLog::query()
                ->where('id', $logId)
                ->where('channel', 'in_app')
                ->where('recipient_user_id', $userId)
                ->first(),
            default => RequestCommunicationLog::query()
                ->where('id', $logId)
                ->where('channel', 'in_app')
                ->where('recipient_user_id', $userId)
                ->first(),
        };

        if (! $log) {
            $this->setFeedbackError('Notification not found or not accessible.');

            return;
        }

        if (! $log->read_at) {
            $log->forceFill(['read_at' => now()])->save();
        }

        $this->setFeedback('Notification marked as read.');
    }

    private function applyDeliveryAccessScope(Builder $query): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        $role = (string) $user->role;
        $canViewCompanyLogs = in_array($role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);

        if ($canViewCompanyLogs) {
            return;
        }

        $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('recipient_user_id', (int) $user->id)
                ->orWhereHas('request', fn (Builder $requestQuery) => $requestQuery->where('requested_by', (int) $user->id));
        });
    }

    private function pageName(): string
    {
        return $this->activeTab === 'inbox' ? 'inboxPage' : 'deliveryPage';
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

    private function canViewDeliveryLogs(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user || ! $user->is_active) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canExecuteDeliveryOps(): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user || ! $user->is_active) {
            return false;
        }

        $role = (string) (\Illuminate\Support\Facades\Auth::user()?->role ?? '');

        return in_array($role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }

    private function companyId(): ?int
    {
        $companyId = \Illuminate\Support\Facades\Auth::user()?->company_id;

        return $companyId ? (int) $companyId : null;
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return RequestCommunicationLog::query()
            ->whereRaw('1 = 0')
            ->paginate($this->perPage, pageName: $this->pageName());
    }
}

