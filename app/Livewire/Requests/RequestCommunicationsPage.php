<?php

namespace App\Livewire\Requests;

use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Enums\UserRole;
use App\Services\AssetCommunicationRetryService;
use App\Services\RequestCommunicationRetryService;
use App\Services\VendorCommunicationRetryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Communications Recovery Desk')]
class RequestCommunicationsPage extends Component
{
    use WithPagination;

    private const ALLOWED_TABS = ['inbox', 'delivery'];

    private const ALLOWED_CHANNELS = ['all', 'in_app', 'email', 'sms'];

    private const ALLOWED_STATUSES = ['all', 'queued', 'sent', 'failed', 'skipped'];

    private const ALLOWED_SCOPES = ['all', 'requests', 'vendors', 'assets'];

    private const ALLOWED_PER_PAGE = [10, 25, 50];

    private const MAX_SEARCH_LENGTH = 120;

    private const MAX_QUEUED_OLDER_THAN_MINUTES = 10080;

    public bool $readyToLoad = false;

    public string $activeTab = 'inbox';

    public string $search = '';

    public string $channelFilter = 'all';

    public string $statusFilter = 'all';

    public string $displayScope = 'all';

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
        $user = auth()->user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', SpendRequest::class), 403);
        $this->normalizeUiState();
        if ($this->activeTab === 'delivery' && ! $this->canViewDeliveryLogs()) {
            $this->activeTab = 'inbox';
        }
    }

    public function retryLog(int $logId, RequestCommunicationRetryService $retryService): void
    {
        // Backward-compatible entrypoint used by existing tests and call-sites.
        $this->retryLogBySource('requests', $logId, $retryService, app(VendorCommunicationRetryService::class), app(AssetCommunicationRetryService::class));
    }

    public function retryLogBySource(
        string $source,
        int $logId,
        RequestCommunicationRetryService $requestRetryService,
        VendorCommunicationRetryService $vendorRetryService,
        AssetCommunicationRetryService $assetRetryService
    ): void {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');

            return;
        }

        $normalizedSource = $this->normalizeSource($source);
        if ($normalizedSource === '') {
            $this->setFeedbackError('Communication source is invalid.');

            return;
        }

        $log = $this->findRecoveryLog($normalizedSource, $logId);
        if (! $log) {
            $this->setFeedbackError('Communication record not found.');

            return;
        }

        if (! in_array((string) $log->status, ['failed', 'queued', 'skipped'], true)) {
            $this->setFeedbackError('Only failed, skipped, or queued records can be retried.');

            return;
        }

        $after = match ($normalizedSource) {
            'vendors' => $vendorRetryService->retryLog($log),
            'assets' => $assetRetryService->retryLog($log),
            default => $requestRetryService->retryLog($log),
        };

        $status = ucfirst((string) $after->status);
        $this->setFeedback(sprintf('%s retry completed. Current status: %s.', $this->sourceLabel($normalizedSource), $status));
    }

    public function retryFailed(
        RequestCommunicationRetryService $requestRetryService,
        VendorCommunicationRetryService $vendorRetryService,
        AssetCommunicationRetryService $assetRetryService
    ): void {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');

            return;
        }

        $scope = $this->normalizeScope($this->displayScope);
        $companyId = $this->companyId();
        $parts = [];

        // One desk can execute retries across request/vendor/asset pipelines based on scope.
        if (in_array($scope, ['all', 'requests'], true)) {
            $stats = $requestRetryService->retryFailed($companyId, 300);
            $parts[] = sprintf('Requests retried %d, sent %d, failed %d, skipped %d.', (int) $stats['retried'], (int) $stats['sent'], (int) $stats['failed'], (int) $stats['skipped']);
        }

        if (in_array($scope, ['all', 'vendors'], true)) {
            $stats = $vendorRetryService->retryFailed($companyId, null, 300);
            $parts[] = sprintf('Vendors retried %d, sent %d, failed %d, skipped %d.', (int) $stats['retried'], (int) $stats['sent'], (int) $stats['failed'], (int) $stats['skipped']);
        }

        if (in_array($scope, ['all', 'assets'], true)) {
            $stats = $assetRetryService->retryFailed($companyId, 300);
            $parts[] = sprintf('Assets retried %d, sent %d, failed %d, skipped %d.', (int) $stats['retried'], (int) $stats['sent'], (int) $stats['failed'], (int) $stats['skipped']);
        }

        $this->setFeedback('Recovery retry complete. '.implode(' ', $parts));
    }

    public function processQueuedBacklog(
        RequestCommunicationRetryService $requestRetryService,
        VendorCommunicationRetryService $vendorRetryService,
        AssetCommunicationRetryService $assetRetryService
    ): void {
        if (! $this->canExecuteDeliveryOps()) {
            $this->setFeedbackError('You are not allowed to manage communication retry operations.');

            return;
        }

        $scope = $this->normalizeScope($this->displayScope);
        $olderThan = $this->normalizeQueuedOlderThanMinutes($this->queuedOlderThanMinutes);
        $companyId = $this->companyId();
        $parts = [];

        if (in_array($scope, ['all', 'requests'], true)) {
            $stats = $requestRetryService->processStuckQueued($companyId, $olderThan, 500);
            $parts[] = sprintf(
                'Requests processed %d, sent %d, failed %d, remaining queued %d.',
                (int) $stats['processed'],
                (int) $stats['sent'],
                (int) $stats['failed'],
                (int) $stats['remaining_queued']
            );
        }

        if (in_array($scope, ['all', 'vendors'], true)) {
            $stats = $vendorRetryService->processStuckQueued($companyId, null, $olderThan, 500);
            $parts[] = sprintf(
                'Vendors processed %d, sent %d, failed %d, remaining queued %d.',
                (int) $stats['processed'],
                (int) $stats['sent'],
                (int) $stats['failed'],
                (int) $stats['remaining_queued']
            );
        }

        if (in_array($scope, ['all', 'assets'], true)) {
            $stats = $assetRetryService->processStuckQueued($companyId, $olderThan, 500);
            $parts[] = sprintf(
                'Assets processed %d, sent %d, failed %d, remaining queued %d.',
                (int) $stats['processed'],
                (int) $stats['sent'],
                (int) $stats['failed'],
                (int) $stats['remaining_queued']
            );
        }

        $this->setFeedback('Queued recovery complete. '.implode(' ', $parts));
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedActiveTab(): void
    {
        $this->activeTab = $this->normalizeTab($this->activeTab);
        if ($this->activeTab === 'delivery' && ! $this->canViewDeliveryLogs()) {
            $this->activeTab = 'inbox';
        }

        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedChannelFilter(): void
    {
        $this->channelFilter = $this->normalizeChannel($this->channelFilter);
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->statusFilter = $this->normalizeStatus($this->statusFilter);
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedDisplayScope(): void
    {
        $this->displayScope = $this->normalizeScope($this->displayScope);
        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage($this->perPage);

        $this->resetPage(pageName: $this->pageName());
    }

    public function updatedQueuedOlderThanMinutes(mixed $value): void
    {
        // Allow in-progress typing (empty input) without hydration/type errors.
        if ($value === null || $value === '') {
            return;
        }

        $this->queuedOlderThanMinutes = $this->normalizeQueuedOlderThanMinutes($value);
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
        $normalized = $this->normalizeSource($source);
        if ($normalized === '') {
            $this->setFeedbackError('Notification source is invalid.');

            return;
        }

        $this->markInboxMessageAsRead($normalized, $logId);
    }

    public function markAllRead(): void
    {
        $userId = (int) auth()->id();
        $companyId = $this->companyId() ?? 0;

        // Inbox read-state spans all in-app internal notifications, regardless of module.
        RequestCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        VendorCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AssetCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->setFeedback('All inbox notifications marked as read.');
    }

    public function render(): View
    {
        $this->normalizeUiState();

        $canViewDeliveryLogs = $this->canViewDeliveryLogs();
        if ($this->activeTab === 'delivery' && ! $canViewDeliveryLogs) {
            $this->activeTab = 'inbox';
        }

        $companyId = $this->companyId() ?? 0;
        $olderThan = $this->normalizeQueuedOlderThanMinutes($this->queuedOlderThanMinutes);

        $requestInboxUnreadCount = RequestCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->whereNull('read_at')
            ->count();

        $vendorInboxUnreadCount = VendorCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->whereNull('read_at')
            ->count();

        $assetInboxUnreadCount = AssetCommunicationLog::query()
            ->where('company_id', $companyId)
            ->where('channel', 'in_app')
            ->where('recipient_user_id', (int) auth()->id())
            ->whereNull('read_at')
            ->count();

        $inboxUnreadCount = $requestInboxUnreadCount + $vendorInboxUnreadCount + $assetInboxUnreadCount;
        $canManageDeliveryOps = $this->canExecuteDeliveryOps();

        $recoverySummary = [
            'totals' => ['failed' => 0, 'queued_stuck' => 0, 'active' => 0],
            'modules' => [
                'requests' => ['label' => 'Requests', 'failed' => 0, 'queued_stuck' => 0],
                'vendors' => ['label' => 'Vendors', 'failed' => 0, 'queued_stuck' => 0],
                'assets' => ['label' => 'Assets', 'failed' => 0, 'queued_stuck' => 0],
            ],
            'channels' => [],
            'recipient_issues' => [],
        ];

        if ($canViewDeliveryLogs) {
            $recoverySummary = $this->recoverySummary($companyId, $olderThan, $this->displayScope);
        }

        if (! $this->readyToLoad) {
            $communications = $this->emptyPaginator();
        } elseif ($this->activeTab === 'inbox') {
            $communications = $this->inboxMessagesQuery()->paginate($this->perPage, pageName: $this->pageName());
        } else {
            $communications = $this->recoveryLogsQuery()->paginate($this->perPage, pageName: $this->pageName());
        }

        return view('livewire.requests.request-communications-page', [
            'communications' => $communications,
            'inboxUnreadCount' => $inboxUnreadCount,
            'channels' => ['in_app', 'email', 'sms'],
            'statuses' => ['queued', 'sent', 'failed', 'skipped'],
            'scopes' => [
                'all' => 'All modules',
                'requests' => 'Requests',
                'vendors' => 'Vendors',
                'assets' => 'Assets',
            ],
            'canViewDeliveryLogs' => $canViewDeliveryLogs,
            'canManageDeliveryOps' => $canManageDeliveryOps,
            'recoverySummary' => $recoverySummary,
        ]);
    }

    private function recoveryLogsQuery(): QueryBuilder
    {
        $companyId = $this->companyId() ?? 0;
        $cutoff = now()->subMinutes($this->normalizeQueuedOlderThanMinutes($this->queuedOlderThanMinutes));

        $query = $this->recoveryBaseQuery($companyId)
            ->leftJoin('requests as requests', 'requests.id', '=', 'logs.request_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'logs.vendor_id')
            ->leftJoin('vendor_invoices as invoices', 'invoices.id', '=', 'logs.vendor_invoice_id')
            ->leftJoin('assets as assets', 'assets.id', '=', 'logs.asset_id')
            ->leftJoin('users as recipients', 'recipients.id', '=', 'logs.recipient_user_id')
            ->select([
                'logs.source_section',
                'logs.id',
                'logs.event',
                'logs.channel',
                'logs.status',
                'logs.message',
                'logs.read_at',
                'logs.created_at',
                'logs.recipient_user_id',
                'logs.recipient_email',
                'logs.recipient_phone',
                'logs.request_id',
                'logs.vendor_id',
                'logs.vendor_invoice_id',
                'logs.asset_id',
                'requests.request_code as request_code',
                'requests.title as request_title',
                'vendors.name as vendor_name',
                'invoices.invoice_number as invoice_number',
                'assets.asset_code as asset_code',
                'assets.name as asset_name',
                'recipients.name as recipient_name',
                'recipients.email as recipient_user_email',
                'recipients.phone as recipient_user_phone',
            ]);

        if ($this->displayScope !== 'all') {
            $query->where('logs.source_section', $this->normalizeScope($this->displayScope));
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function (QueryBuilder $builder) use ($search): void {
                $builder
                    ->where('logs.event', 'like', '%'.$search.'%')
                    ->orWhere('logs.message', 'like', '%'.$search.'%')
                    ->orWhere('requests.request_code', 'like', '%'.$search.'%')
                    ->orWhere('requests.title', 'like', '%'.$search.'%')
                    ->orWhere('vendors.name', 'like', '%'.$search.'%')
                    ->orWhere('invoices.invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('assets.asset_code', 'like', '%'.$search.'%')
                    ->orWhere('assets.name', 'like', '%'.$search.'%')
                    ->orWhere('recipients.name', 'like', '%'.$search.'%')
                    ->orWhere('logs.recipient_email', 'like', '%'.$search.'%')
                    ->orWhere('logs.recipient_phone', 'like', '%'.$search.'%');
            });
        }

        if ($this->channelFilter !== 'all') {
            $query->where('logs.channel', $this->channelFilter);
        }

        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'queued') {
                $query->where('logs.status', 'queued')
                    ->where('logs.created_at', '<=', $cutoff);
            } else {
                $query->where('logs.status', $this->statusFilter);
            }
        } else {
            // Keep the recovery table focused on actionable queued backlog rows.
            $query->where(function (QueryBuilder $builder) use ($cutoff): void {
                $builder->where('logs.status', '!=', 'queued')
                    ->orWhere('logs.created_at', '<=', $cutoff);
            });
        }

        return $query->orderByDesc('logs.created_at')->orderByDesc('logs.id');
    }

    private function inboxMessagesQuery(): QueryBuilder
    {
        $userId = (int) auth()->id();
        $companyId = $this->companyId() ?? 0;

        // Request-origin in-app notifications.
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
                DB::raw('NULL as asset_id'),
            ])
            ->where('request_communication_logs.company_id', $companyId)
            ->where('request_communication_logs.channel', 'in_app')
            ->where('request_communication_logs.recipient_user_id', $userId);

        // Vendor/payables-origin in-app notifications.
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
                DB::raw('NULL as asset_id'),
            ])
            ->where('vendor_communication_logs.company_id', $companyId)
            ->where('vendor_communication_logs.channel', 'in_app')
            ->where('vendor_communication_logs.recipient_user_id', $userId);

        // Asset-origin in-app notifications.
        $assetInbox = DB::table('asset_communication_logs')
            ->select([
                DB::raw("'assets' as source_section"),
                'asset_communication_logs.id',
                'asset_communication_logs.event',
                'asset_communication_logs.channel',
                'asset_communication_logs.status',
                'asset_communication_logs.message',
                'asset_communication_logs.read_at',
                'asset_communication_logs.created_at',
                'asset_communication_logs.recipient_user_id',
                DB::raw('NULL as request_id'),
                DB::raw('NULL as vendor_id'),
                DB::raw('NULL as vendor_invoice_id'),
                'asset_communication_logs.asset_id',
            ])
            ->where('asset_communication_logs.company_id', $companyId)
            ->where('asset_communication_logs.channel', 'in_app')
            ->where('asset_communication_logs.recipient_user_id', $userId);

        $combined = $requestInbox->unionAll($vendorInbox)->unionAll($assetInbox);

        $query = DB::query()
            ->fromSub($combined, 'messages')
            ->leftJoin('requests as requests', 'requests.id', '=', 'messages.request_id')
            ->leftJoin('vendors as vendors', 'vendors.id', '=', 'messages.vendor_id')
            ->leftJoin('vendor_invoices as invoices', 'invoices.id', '=', 'messages.vendor_invoice_id')
            ->leftJoin('assets as assets', 'assets.id', '=', 'messages.asset_id')
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
                'messages.asset_id',
                'requests.request_code as request_code',
                'requests.title as request_title',
                'vendors.name as vendor_name',
                'invoices.invoice_number as invoice_number',
                'assets.asset_code as asset_code',
                'assets.name as asset_name',
                'recipients.name as recipient_name',
            ]);

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function (QueryBuilder $builder) use ($search): void {
                $builder
                    ->where('messages.event', 'like', '%'.$search.'%')
                    ->orWhere('messages.message', 'like', '%'.$search.'%')
                    ->orWhere('requests.request_code', 'like', '%'.$search.'%')
                    ->orWhere('requests.title', 'like', '%'.$search.'%')
                    ->orWhere('vendors.name', 'like', '%'.$search.'%')
                    ->orWhere('invoices.invoice_number', 'like', '%'.$search.'%')
                    ->orWhere('assets.asset_code', 'like', '%'.$search.'%')
                    ->orWhere('assets.name', 'like', '%'.$search.'%')
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

    private function recoveryBaseQuery(int $companyId): QueryBuilder
    {
        // Normalize module-specific communication tables into one queryable recovery stream.
        $requestLogs = DB::table('request_communication_logs')
            ->select([
                DB::raw("'requests' as source_section"),
                'request_communication_logs.id',
                'request_communication_logs.company_id',
                'request_communication_logs.event',
                'request_communication_logs.channel',
                'request_communication_logs.status',
                'request_communication_logs.message',
                'request_communication_logs.read_at',
                'request_communication_logs.created_at',
                'request_communication_logs.recipient_user_id',
                DB::raw('NULL as recipient_email'),
                DB::raw('NULL as recipient_phone'),
                'request_communication_logs.request_id',
                DB::raw('NULL as vendor_id'),
                DB::raw('NULL as vendor_invoice_id'),
                DB::raw('NULL as asset_id'),
            ])
            ->where('request_communication_logs.company_id', $companyId);

        $vendorLogs = DB::table('vendor_communication_logs')
            ->select([
                DB::raw("'vendors' as source_section"),
                'vendor_communication_logs.id',
                'vendor_communication_logs.company_id',
                'vendor_communication_logs.event',
                'vendor_communication_logs.channel',
                'vendor_communication_logs.status',
                'vendor_communication_logs.message',
                'vendor_communication_logs.read_at',
                'vendor_communication_logs.created_at',
                'vendor_communication_logs.recipient_user_id',
                'vendor_communication_logs.recipient_email',
                'vendor_communication_logs.recipient_phone',
                DB::raw('NULL as request_id'),
                'vendor_communication_logs.vendor_id',
                'vendor_communication_logs.vendor_invoice_id',
                DB::raw('NULL as asset_id'),
            ])
            ->where('vendor_communication_logs.company_id', $companyId);

        $assetLogs = DB::table('asset_communication_logs')
            ->select([
                DB::raw("'assets' as source_section"),
                'asset_communication_logs.id',
                'asset_communication_logs.company_id',
                'asset_communication_logs.event',
                'asset_communication_logs.channel',
                'asset_communication_logs.status',
                'asset_communication_logs.message',
                'asset_communication_logs.read_at',
                'asset_communication_logs.created_at',
                'asset_communication_logs.recipient_user_id',
                'asset_communication_logs.recipient_email',
                'asset_communication_logs.recipient_phone',
                DB::raw('NULL as request_id'),
                DB::raw('NULL as vendor_id'),
                DB::raw('NULL as vendor_invoice_id'),
                'asset_communication_logs.asset_id',
            ])
            ->where('asset_communication_logs.company_id', $companyId);

        $combined = $requestLogs->unionAll($vendorLogs)->unionAll($assetLogs);

        return DB::query()->fromSub($combined, 'logs');
    }

    /**
     * @return array{
     *   totals: array{failed:int,queued_stuck:int,active:int},
     *   modules: array<string, array{label:string,failed:int,queued_stuck:int}>,
     *   channels: array<int, array{channel:string,label:string,failed:int,queued_stuck:int,total:int}>,
     *   recipient_issues: array<int, array{key:string,label:string,count:int}>
     * }
     */
    private function recoverySummary(int $companyId, int $olderThanMinutes, string $scope): array
    {
        $normalizedScope = $this->normalizeScope($scope);
        $cutoff = now()->subMinutes(max(0, $olderThanMinutes));

        $rows = $this->recoveryBaseQuery($companyId)
            ->when($normalizedScope !== 'all', fn (QueryBuilder $query) => $query->where('logs.source_section', $normalizedScope))
            ->where(function (QueryBuilder $query) use ($cutoff): void {
                $query->where('logs.status', 'failed')
                    ->orWhere(function (QueryBuilder $queued) use ($cutoff): void {
                        $queued->where('logs.status', 'queued')->where('logs.created_at', '<=', $cutoff);
                    });
            })
            ->select([
                'logs.source_section',
                'logs.channel',
                'logs.status',
                'logs.message',
                'logs.recipient_email',
                'logs.recipient_phone',
                'logs.recipient_user_id',
                'logs.created_at',
            ])
            ->limit(3000)
            ->get();

        $modules = [
            'requests' => ['label' => 'Requests', 'failed' => 0, 'queued_stuck' => 0],
            'vendors' => ['label' => 'Vendors', 'failed' => 0, 'queued_stuck' => 0],
            'assets' => ['label' => 'Assets', 'failed' => 0, 'queued_stuck' => 0],
        ];

        $channels = [];
        $recipientIssues = [
            'missing_email' => ['key' => 'missing_email', 'label' => 'Missing recipient email', 'count' => 0],
            'missing_phone' => ['key' => 'missing_phone', 'label' => 'Missing recipient phone', 'count' => 0],
            'channel_config' => ['key' => 'channel_config', 'label' => 'Channel disabled or unconfigured', 'count' => 0],
            'unsupported_channel' => ['key' => 'unsupported_channel', 'label' => 'Unsupported channel', 'count' => 0],
            'provider_error' => ['key' => 'provider_error', 'label' => 'Provider/send error', 'count' => 0],
            'missing_target' => ['key' => 'missing_target', 'label' => 'No recipient target', 'count' => 0],
            'other' => ['key' => 'other', 'label' => 'Other failed reason', 'count' => 0],
        ];

        foreach ($rows as $row) {
            $source = in_array((string) $row->source_section, ['requests', 'vendors', 'assets'], true)
                ? (string) $row->source_section
                : 'requests';

            if ((string) $row->status === 'failed') {
                $modules[$source]['failed']++;
            }

            if ((string) $row->status === 'queued') {
                $modules[$source]['queued_stuck']++;
            }

            $channel = trim((string) ($row->channel ?? 'unknown'));
            if ($channel === '') {
                $channel = 'unknown';
            }

            if (! isset($channels[$channel])) {
                $channels[$channel] = [
                    'channel' => $channel,
                    'label' => strtoupper(str_replace('_', ' ', $channel)),
                    'failed' => 0,
                    'queued_stuck' => 0,
                    'total' => 0,
                ];
            }

            if ((string) $row->status === 'failed') {
                $channels[$channel]['failed']++;
                $issueKey = $this->classifyRecipientIssue($row);
                $recipientIssues[$issueKey]['count']++;
            }

            if ((string) $row->status === 'queued') {
                $channels[$channel]['queued_stuck']++;
            }

            $channels[$channel]['total'] = $channels[$channel]['failed'] + $channels[$channel]['queued_stuck'];
        }

        $totalFailed = (int) array_sum(array_map(static fn (array $module): int => (int) $module['failed'], $modules));
        $totalQueuedStuck = (int) array_sum(array_map(static fn (array $module): int => (int) $module['queued_stuck'], $modules));

        usort($channels, static function (array $left, array $right): int {
            return $right['total'] <=> $left['total'];
        });

        $recipientIssues = array_values(array_filter($recipientIssues, static fn (array $item): bool => (int) $item['count'] > 0));

        return [
            'totals' => [
                'failed' => $totalFailed,
                'queued_stuck' => $totalQueuedStuck,
                'active' => $totalFailed + $totalQueuedStuck,
            ],
            'modules' => $modules,
            'channels' => $channels,
            'recipient_issues' => $recipientIssues,
        ];
    }

    private function classifyRecipientIssue(object $row): string
    {
        $message = Str::lower((string) ($row->message ?? ''));

        if (str_contains($message, 'recipient email is missing')) {
            return 'missing_email';
        }

        if (str_contains($message, 'recipient phone is missing')) {
            return 'missing_phone';
        }

        if (str_contains($message, 'disabled or not configured')) {
            return 'channel_config';
        }

        if (str_contains($message, 'unsupported communication channel')) {
            return 'unsupported_channel';
        }

        if (str_contains($message, 'unexpectedly') || str_contains($message, 'while sending')) {
            return 'provider_error';
        }

        $recipientEmail = trim((string) ($row->recipient_email ?? ''));
        $recipientPhone = trim((string) ($row->recipient_phone ?? ''));
        $recipientUserId = (int) ($row->recipient_user_id ?? 0);

        if ($recipientEmail === '' && $recipientPhone === '' && $recipientUserId === 0) {
            return 'missing_target';
        }

        return 'other';
    }

    private function findRecoveryLog(string $source, int $logId): RequestCommunicationLog|VendorCommunicationLog|AssetCommunicationLog|null
    {
        $companyId = $this->companyId() ?? 0;

        return match ($source) {
            'vendors' => VendorCommunicationLog::query()
                ->where('company_id', $companyId)
                ->find($logId),
            'assets' => AssetCommunicationLog::query()
                ->where('company_id', $companyId)
                ->find($logId),
            default => RequestCommunicationLog::query()
                ->where('company_id', $companyId)
                ->find($logId),
        };
    }

    private function markInboxMessageAsRead(string $source, int $logId): void
    {
        $userId = (int) auth()->id();
        $companyId = $this->companyId() ?? 0;

        $log = match ($source) {
            'vendors' => VendorCommunicationLog::query()
                ->where('company_id', $companyId)
                ->where('id', $logId)
                ->where('channel', 'in_app')
                ->where('recipient_user_id', $userId)
                ->first(),
            'assets' => AssetCommunicationLog::query()
                ->where('company_id', $companyId)
                ->where('id', $logId)
                ->where('channel', 'in_app')
                ->where('recipient_user_id', $userId)
                ->first(),
            default => RequestCommunicationLog::query()
                ->where('company_id', $companyId)
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
        $user = auth()->user();
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
        $user = auth()->user();
        if (! $user || ! $user->is_active) {
            return false;
        }

        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }

    private function companyId(): ?int
    {
        $companyId = auth()->user()?->company_id;

        return $companyId ? (int) $companyId : null;
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'vendors' => 'Vendors',
            'assets' => 'Assets',
            default => 'Requests',
        };
    }

    private function normalizeScope(string $scope): string
    {
        $normalized = strtolower(trim($scope));

        return in_array($normalized, self::ALLOWED_SCOPES, true)
            ? $normalized
            : 'all';
    }

    private function normalizeSource(string $source): string
    {
        $normalized = strtolower(trim($source));

        return in_array($normalized, ['requests', 'vendors', 'assets'], true)
            ? $normalized
            : '';
    }

    private function normalizeUiState(): void
    {
        $this->activeTab = $this->normalizeTab($this->activeTab);
        $this->search = $this->normalizeSearch($this->search);
        $this->channelFilter = $this->normalizeChannel($this->channelFilter);
        $this->statusFilter = $this->normalizeStatus($this->statusFilter);
        $this->displayScope = $this->normalizeScope($this->displayScope);
        $this->perPage = $this->normalizePerPage($this->perPage);
    }

    private function normalizeTab(string $tab): string
    {
        $normalized = strtolower(trim($tab));

        return in_array($normalized, self::ALLOWED_TABS, true)
            ? $normalized
            : 'inbox';
    }

    private function normalizeSearch(string $search): string
    {
        return mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH);
    }

    private function normalizeChannel(string $channel): string
    {
        $normalized = strtolower(trim($channel));

        return in_array($normalized, self::ALLOWED_CHANNELS, true)
            ? $normalized
            : 'all';
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, self::ALLOWED_STATUSES, true)
            ? $normalized
            : 'all';
    }

    private function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, self::ALLOWED_PER_PAGE, true)
            ? $perPage
            : self::ALLOWED_PER_PAGE[0];
    }

    private function normalizeQueuedOlderThanMinutes(mixed $value): int
    {
        return min(
            self::MAX_QUEUED_OLDER_THAN_MINUTES,
            max(0, (int) $value)
        );
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return RequestCommunicationLog::query()
            ->whereRaw('1 = 0')
            ->paginate($this->perPage, pageName: $this->pageName());
    }
}


