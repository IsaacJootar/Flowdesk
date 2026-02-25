<?php

namespace App\Livewire\Requests;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Requests\CreateRequestDraft;
use App\Actions\Requests\AddRequestComment;
use App\Actions\Requests\DecideSpendRequest;
use App\Actions\Requests\SubmitSpendRequest;
use App\Actions\Requests\UploadRequestAttachment;
use App\Actions\Requests\UpdateRequestDraft;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\RequestComment;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\RequestAttachment;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanySpendCategory;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ExpensePolicyResolver;
use App\Services\RequestApprovalRouter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class RequestsPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public string $departmentFilter = 'all';

    public string $scopeFilter = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public bool $showFormModal = false;

    public bool $showViewModal = false;

    public bool $isEditing = false;

    public ?int $editingRequestId = null;

    public ?int $selectedRequestId = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedRequest = null;

    public ?string $feedbackMessage = null;

    public ?string $feedbackWarning = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $decisionComment = '';

    /** @var array<string, mixed> */
    public array $form = [
        'type' => '',
        'title' => '',
        'description' => '',
        'department_id' => '',
        'vendor_id' => '',
        'workflow_id' => '',
        'currency' => 'NGN',
        'amount' => '',
        'needed_by' => '',
        'start_date' => '',
        'end_date' => '',
        'destination' => '',
        'leave_type' => '',
        'handover_user_id' => '',
    ];

    /** @var array<int, array{name: string, description: string, quantity: string, unit_cost: string, vendor_id: string, category: string}> */
    public array $lineItems = [];

    /** @var array<string, array<string, mixed>> */
    public array $requestTypeMap = [];

    /** @var array<int, string> */
    public array $submitNotificationChannels = [];

    /** @var array<string, array{label: string, enabled: bool, configured: bool, selectable: bool}> */
    public array $submitChannelPolicies = [];

    /** @var array<int, string> */
    public array $decisionNotificationChannels = [];

    /** @var array<string, array{label: string, enabled: bool, configured: bool, selectable: bool}> */
    public array $decisionChannelPolicies = [];

    public string $threadComment = '';

    /** @var array<int, mixed> */
    public array $newAttachments = [];

    /** @var array<int, mixed> */
    public array $viewNewAttachments = [];

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        abort_unless($user && Gate::forUser($user)->allows('viewAny', SpendRequest::class), 403);

        // Deep-link support: allow other modules to prefill request lookup on load.
        $deepLinkRequestCode = trim((string) request()->query('request_code', ''));
        if ($deepLinkRequestCode !== '') {
            $this->search = mb_substr($deepLinkRequestCode, 0, 120);
            $this->scopeFilter = 'all';
        } else {
            $deepLinkSearch = trim((string) request()->query('search', ''));
            if ($deepLinkSearch !== '') {
                $this->search = mb_substr($deepLinkSearch, 0, 120);
                $this->scopeFilter = 'all';
            }
        }

        $this->refreshRequestTypeMap();
        $this->form['currency'] = $this->companyCurrency();
        $this->form['type'] = '';
        $this->form['department_id'] = $this->currentUserDepartmentId() ? (string) $this->currentUserDepartmentId() : '';
        $this->addLineItem();

        // Optional deep-link: open a specific request modal directly from another module.
        $openRequestId = (int) request()->query('open_request_id', 0);
        if ($openRequestId > 0 && SpendRequest::query()->whereKey($openRequestId)->exists()) {
            $this->openViewModal($openRequestId);
        }
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

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedScopeFilter(): void
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

    public function updatedFormType(): void
    {
        if (! $this->currentTypeRequiresLineItems()) {
            $this->lineItems = [];
            $this->addLineItem();
        }
    }

    public function openCreateModal(): void
    {
        if (Gate::denies('create', SpendRequest::class)) {
            $this->setFeedbackError('You are not allowed to create requests.');

            return;
        }

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showViewModal = false;
        $this->isEditing = false;
        $this->editingRequestId = null;
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $requestId): void
    {
        $request = $this->findRequestOrFail($requestId);
        if (Gate::denies('update', $request)) {
            $this->setFeedbackError('You are not allowed to edit this request.');

            return;
        }

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showViewModal = false;
        $this->isEditing = true;
        $this->editingRequestId = $request->id;
        $this->fillFormFromRequest($request);
        $this->showFormModal = true;
    }

    public function openViewModal(int $requestId): void
    {
        $request = $this->loadRequestForView($requestId);
        if (Gate::denies('view', $request)) {
            $this->setFeedbackError('You do not have access to view this request.');

            return;
        }

        $this->resetValidation();
        $this->feedbackError = null;
        $this->showFormModal = false;
        $this->selectedRequestId = $request->id;
        $this->decisionComment = '';
        $this->showViewModal = true;
        $this->markInAppNotificationsAsRead($request->id);
        $this->fillSelectedRequestData($request);
        $this->prepareSubmitChannels($request);
        $this->prepareDecisionChannels($request);
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->isEditing = false;
        $this->editingRequestId = null;
        $this->newAttachments = [];
        $this->resetValidation();
        $this->resetForm();
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->selectedRequestId = null;
        $this->selectedRequest = null;
        $this->decisionComment = '';
        $this->threadComment = '';
        $this->submitNotificationChannels = [];
        $this->submitChannelPolicies = [];
        $this->decisionNotificationChannels = [];
        $this->decisionChannelPolicies = [];
        $this->viewNewAttachments = [];
        $this->resetValidation();
    }

    public function addThreadComment(AddRequestComment $addRequestComment): void
    {
        if (! $this->selectedRequestId) {
            return;
        }

        $request = $this->findRequestOrFail($this->selectedRequestId);
        $this->feedbackError = null;

        try {
            $addRequestComment(\Illuminate\Support\Facades\Auth::user(), $request, [
                'body' => $this->threadComment,
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to add comment right now.');

            return;
        }

        $this->threadComment = '';
        $this->markInAppNotificationsAsRead($request->id);
        $this->setFeedback('Comment added to request thread.');
        $this->fillSelectedRequestData($this->loadRequestForView($request->id));
    }

    public function uploadSelectedRequestAttachments(UploadRequestAttachment $uploadRequestAttachment): void
    {
        if (! $this->selectedRequestId) {
            return;
        }

        if (empty($this->viewNewAttachments)) {
            $this->setFeedbackError('Select at least one attachment to upload.');

            return;
        }

        $this->feedbackError = null;
        $this->validate([
            'viewNewAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ], [
            'viewNewAttachments.*.max' => 'Each attachment must be 10MB or less.',
            'viewNewAttachments.*.mimes' => 'Only PDF and image files are supported.',
        ]);

        $request = $this->findRequestOrFail($this->selectedRequestId);
        if (Gate::denies('uploadAttachment', $request)) {
            $this->setFeedbackError('You are not allowed to upload attachments for this request.');

            return;
        }

        try {
            foreach ($this->viewNewAttachments as $file) {
                if ($file) {
                    $uploadRequestAttachment(\Illuminate\Support\Facades\Auth::user(), $request, $file);
                }
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to upload attachment(s) right now.');

            return;
        }

        $this->viewNewAttachments = [];
        $this->setFeedback('Attachment(s) uploaded successfully.');
        $this->fillSelectedRequestData($this->loadRequestForView($request->id));
    }

    public function addLineItem(): void
    {
        $this->lineItems[] = [
            'name' => '',
            'description' => '',
            'quantity' => '1',
            'unit_cost' => '',
            'vendor_id' => '',
            'category' => '',
        ];
    }

    public function removeLineItem(int $index): void
    {
        if (count($this->lineItems) <= 1) {
            return;
        }

        unset($this->lineItems[$index]);
        $this->lineItems = array_values($this->lineItems);
    }

    public function saveDraft(
        CreateRequestDraft $createRequestDraft,
        UpdateRequestDraft $updateRequestDraft,
        UploadRequestAttachment $uploadRequestAttachment
    ): void
    {
        $this->feedbackError = null;
        if (! empty($this->newAttachments)) {
            $this->validate([
                'newAttachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            ], [
                'newAttachments.*.max' => 'Each attachment must be 10MB or less.',
                'newAttachments.*.mimes' => 'Only PDF and image files are supported.',
            ]);
        }

        if (! $this->currentUserDepartmentId()) {
            throw ValidationException::withMessages([
                'form.department_id' => 'Your profile has no department. Ask admin (owner) to assign your department before creating requests.',
            ]);
        }

        $payload = $this->formPayload();

        try {
            if ($this->isEditing && $this->editingRequestId) {
                $request = $this->findRequestOrFail($this->editingRequestId);
                $updatedRequest = $updateRequestDraft(\Illuminate\Support\Facades\Auth::user(), $request, $payload);
                $this->attachDraftUploadedFiles($uploadRequestAttachment, $updatedRequest);
                $this->setFeedback('Request draft updated.');
            } else {
                $createdRequest = $createRequestDraft(\Illuminate\Support\Facades\Auth::user(), $payload);
                $this->attachDraftUploadedFiles($uploadRequestAttachment, $createdRequest);
                $this->setFeedback('Request draft created.');
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to save request draft right now.');

            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    public function submitSelectedRequest(SubmitSpendRequest $submitSpendRequest): void
    {
        if (! $this->selectedRequestId) {
            return;
        }

        $this->feedbackError = null;
        $request = $this->findRequestOrFail($this->selectedRequestId);
        $selectableChannels = array_keys(array_filter(
            $this->submitChannelPolicies,
            fn (array $policy): bool => (bool) ($policy['selectable'] ?? false)
        ));
        $selectedChannels = array_values(array_intersect($this->submitNotificationChannels, $selectableChannels));

        if ($selectableChannels !== [] && $selectedChannels === []) {
            throw ValidationException::withMessages([
                'submitNotificationChannels' => 'Select at least one notification channel before submitting.',
            ]);
        }

        try {
            $updated = $submitSpendRequest(
                \Illuminate\Support\Facades\Auth::user(),
                $request,
                $selectableChannels === [] ? null : $selectedChannels
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to submit request now.');

            return;
        }

        $this->setFeedback('Request submitted for approval.');
        $warnings = $this->policyWarningsFromRequest($updated);
        if ($warnings !== []) {
            $this->setFeedbackWarning(implode(' ', $warnings));
        }
        $this->markInAppNotificationsAsRead($updated->id);
        $this->fillSelectedRequestData($updated);
        $this->prepareDecisionChannels($updated);
        $this->resetPage();
    }

    public function approveSelectedRequest(DecideSpendRequest $decideSpendRequest): void
    {
        $this->decideSelectedRequest($decideSpendRequest, 'approve');
    }

    public function rejectSelectedRequest(DecideSpendRequest $decideSpendRequest): void
    {
        $this->decideSelectedRequest($decideSpendRequest, 'reject');
    }

    public function returnSelectedRequest(DecideSpendRequest $decideSpendRequest): void
    {
        $this->decideSelectedRequest($decideSpendRequest, 'return');
    }

    public function createExpenseFromSelectedRequest(
        CreateExpense $createExpense,
        ExpensePolicyResolver $expensePolicyResolver
    ): void
    {
        if (! $this->selectedRequestId) {
            return;
        }

        $this->feedbackError = null;
        $request = $this->loadRequestForView($this->selectedRequestId);

        if (Gate::denies('create', Expense::class)) {
            $this->setFeedbackError('You are not allowed to create expenses from requests.');

            return;
        }

        if ((string) $request->status !== 'approved') {
            $this->setFeedbackError('Only approved requests can be converted to expense records.');

            return;
        }

        $existingExpense = Expense::query()
            ->where('request_id', (int) $request->id)
            ->latest('id')
            ->first();

        if ($existingExpense) {
            $this->setFeedbackError('This request already has a linked expense record.');

            return;
        }

        $fallbackVendorId = $request->items
            ->first(fn ($item): bool => ! empty($item->vendor_id))
            ?->vendor_id;
        $amount = (int) ($request->approved_amount ?: $request->amount);
        $payload = [
            'department_id' => (int) $request->department_id,
            'vendor_id' => $request->vendor_id ?: ($fallbackVendorId ? (int) $fallbackVendorId : null),
            'title' => sprintf('%s - %s', (string) $request->request_code, (string) $request->title),
            'description' => $this->nullableString($request->description),
            'amount' => $amount,
            'expense_date' => now()->toDateString(),
            'payment_method' => null,
            'paid_by_user_id' => (int) \Illuminate\Support\Facades\Auth::id(),
            'is_direct' => false,
            'request_id' => (int) $request->id,
        ];

        $permissionDecision = $expensePolicyResolver->canCreateFromRequest(
            user: \Illuminate\Support\Facades\Auth::user(),
            departmentId: (int) $request->department_id,
            amount: $amount
        );
        if (! $permissionDecision['allowed']) {
            $this->setFeedbackError((string) ($permissionDecision['reason'] ?? 'You are not allowed to create expenses from requests.'));

            return;
        }

        try {
            $expense = $createExpense(\Illuminate\Support\Facades\Auth::user(), $payload);
        } catch (ValidationException $exception) {
            if (array_key_exists('authorization', $exception->errors())) {
                $this->setFeedbackError((string) ($exception->errors()['authorization'][0] ?? 'You are not allowed to create expenses from requests.'));

                return;
            }

            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to create expense from this request right now.');

            return;
        }

        $request->forceFill([
            'paid_amount' => (int) $expense->amount,
            'updated_by' => (int) \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->setFeedback('Expense created from approved request.');
        $this->fillSelectedRequestData($this->loadRequestForView((int) $request->id));
        $this->resetPage();
    }

    public function render(): View
    {
        $this->refreshRequestTypeMap();
        $approvableRequestIds = $this->approvableRequestIds();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $vendors = Vendor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $workflows = ApprovalWorkflow::query()
            ->where('applies_to', 'request')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $requestTypes = CompanyRequestType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'requires_amount',
                'requires_line_items',
                'requires_date_range',
                'requires_vendor',
                'requires_attachments',
            ]);

        $spendCategories = CompanySpendCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $baseRequestQuery = $this->requestQuery(
            approvableIdsOverride: $approvableRequestIds,
            actedRequestIdsOverride: $this->actedOnRequestIds(),
            decidedByMeIdsOverride: $this->decidedByMeRequestIds(),
        );
        $baseAnalyticsQuery = $this->requestAnalyticsQuery(
            approvableIdsOverride: $approvableRequestIds,
            actedRequestIdsOverride: $this->actedOnRequestIds(),
            decidedByMeIdsOverride: $this->decidedByMeRequestIds(),
        );

        $requests = $this->readyToLoad
            ? (clone $baseRequestQuery)->paginate($this->perPage)
            : SpendRequest::query()->whereRaw('1 = 0')->paginate($this->perPage);
        $requestAnalytics = $this->readyToLoad
            ? $this->buildRequestAnalytics(
                baseQuery: clone $baseAnalyticsQuery,
                approvableRequestIds: $approvableRequestIds,
                requestTypeCodes: $requestTypes->pluck('code')->map(fn ($code): string => (string) $code)->all(),
            )
            : $this->buildRequestAnalytics(
                baseQuery: SpendRequest::query()->whereRaw('1 = 0'),
                approvableRequestIds: [],
                requestTypeCodes: $requestTypes->pluck('code')->map(fn ($code): string => (string) $code)->all(),
            );
        $rowApprovalContexts = $this->readyToLoad
            ? $this->buildRowApprovalContexts($requests->items(), $approvableRequestIds)
            : [];

        return view('livewire.requests.requests-page', [
            'requests' => $requests,
            'departments' => $departments,
            'vendors' => $vendors,
            'workflows' => $workflows,
            'requestTypes' => $requestTypes,
            'spendCategories' => $spendCategories,
            'users' => $users,
            'statuses' => ['draft', 'in_review', 'approved', 'rejected', 'returned'],
            'approvableRequestIds' => $approvableRequestIds,
            'requestAnalytics' => $requestAnalytics,
            'rowApprovalContexts' => $rowApprovalContexts,
            'currentUserDepartmentName' => $this->currentUserDepartmentName(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function selectedRequestSummary(): array
    {
        return $this->selectedRequest ?? [];
    }

    private function decideSelectedRequest(DecideSpendRequest $decideSpendRequest, string $action): void
    {
        if (! $this->selectedRequestId) {
            return;
        }

        $this->feedbackError = null;
        $request = $this->findRequestOrFail($this->selectedRequestId);
        $selectableChannels = array_keys(array_filter(
            $this->decisionChannelPolicies,
            fn (array $policy): bool => (bool) ($policy['selectable'] ?? false)
        ));
        $selectedChannels = array_values(array_intersect($this->decisionNotificationChannels, $selectableChannels));

        if ($selectableChannels !== [] && $selectedChannels === []) {
            throw ValidationException::withMessages([
                'decisionNotificationChannels' => 'Select at least one notification channel for this approval action.',
            ]);
        }

        try {
            $updated = $decideSpendRequest(\Illuminate\Support\Facades\Auth::user(), $request, [
                'action' => $action,
                'comment' => $this->decisionComment,
            ], $selectableChannels === [] ? null : $selectedChannels);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_key_exists('notification_channels', $errors)) {
                $errors['decisionNotificationChannels'] = $errors['notification_channels'];
                unset($errors['notification_channels']);
            }
            throw ValidationException::withMessages($this->normalizeValidationErrors($errors));
        } catch (Throwable) {
            $this->setFeedbackError('Unable to complete approval action right now.');

            return;
        }

        $this->decisionComment = '';
        $message = match ($action) {
            'approve' => $updated->status === 'approved' ? 'Request fully approved.' : 'Step approved. Request moved to next approver.',
            'reject' => 'Request rejected.',
            default => 'Request returned for update.',
        };

        // Keep UX continuity after decision: items may leave "awaiting my approval" scope immediately.
        if ($this->scopeFilter === 'pending_my_approval') {
            $this->scopeFilter = 'all';
            $message .= ' Switched to All accessible requests.';
        }

        $this->setFeedback($message);
        $this->markInAppNotificationsAsRead($updated->id);
        $this->fillSelectedRequestData($updated);
        $this->prepareDecisionChannels($updated);
        $this->resetPage();
    }

    private function requestQuery(
        ?array $approvableIdsOverride = null,
        ?array $actedRequestIdsOverride = null,
        ?array $decidedByMeIdsOverride = null
    ): Builder
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $approvableIds = $approvableIdsOverride ?? $this->approvableRequestIds();
        $actedRequestIds = $actedRequestIdsOverride ?? $this->actedOnRequestIds();
        $decidedByMeIds = $decidedByMeIdsOverride ?? $this->decidedByMeRequestIds();
        $pendingIds = $this->scopeFilter === 'pending_my_approval' ? $approvableIds : null;

        // List query includes eager loads + item counts for table rendering only.
        return SpendRequest::query()
            ->with(['requester:id,name,avatar_path,gender,updated_at', 'department:id,name', 'vendor:id,name'])
            ->withCount('items')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('request_code', 'like', '%'.$this->search.'%')
                        ->orWhere('title', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn ($requesterQuery) => $requesterQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('metadata->type', $this->typeFilter))
            ->when($this->departmentFilter !== 'all', fn ($query) => $query->where('department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', function ($query): void {
                $query->where(function ($dateQuery): void {
                    $dateQuery->whereDate('submitted_at', '>=', $this->dateFrom)
                        ->orWhere(function ($fallback): void {
                            $fallback->whereNull('submitted_at')
                                ->whereDate('created_at', '>=', $this->dateFrom);
                        });
                });
            })
            ->when($this->dateTo !== '', function ($query): void {
                $query->where(function ($dateQuery): void {
                    $dateQuery->whereDate('submitted_at', '<=', $this->dateTo)
                        ->orWhere(function ($fallback): void {
                            $fallback->whereNull('submitted_at')
                                ->whereDate('created_at', '<=', $this->dateTo);
                        });
                });
            })
            ->when($this->scopeFilter === 'mine', fn ($query) => $query->where('requested_by', (int) $user->id))
            ->when($this->scopeFilter === 'pending_my_approval', function ($query) use ($pendingIds): void {
                $query->whereIn('id', empty($pendingIds) ? [0] : $pendingIds);
            })
            ->when($this->scopeFilter === 'decided_by_me', function ($query) use ($decidedByMeIds): void {
                $query->whereIn('id', empty($decidedByMeIds) ? [0] : $decidedByMeIds);
            })
            ->when($this->scopeFilter === 'all', function ($query) use ($user, $approvableIds, $actedRequestIds): void {
                if ($this->canViewAllRequests($user)) {
                    return;
                }

                if ($user->role === UserRole::Manager->value) {
                    if (! $user->department_id) {
                        $query->whereIn('id', [0]);

                        return;
                    }

                    $query->where(function ($inner) use ($user, $approvableIds, $actedRequestIds): void {
                        $inner->where('department_id', (int) $user->department_id);
                        if (! empty($approvableIds)) {
                            $inner->orWhereIn('id', $approvableIds);
                        }
                        if (! empty($actedRequestIds)) {
                            $inner->orWhereIn('id', $actedRequestIds);
                        }
                    });

                    return;
                }

                $query->where(function ($inner) use ($user, $approvableIds, $actedRequestIds): void {
                    $inner->where('requested_by', (int) $user->id);
                    if (! empty($approvableIds)) {
                        $inner->orWhereIn('id', $approvableIds);
                    }
                    if (! empty($actedRequestIds)) {
                        $inner->orWhereIn('id', $actedRequestIds);
                    }
                });
            })
            ->latest('updated_at')
            ->latest('id');
    }

    private function requestAnalyticsQuery(
        ?array $approvableIdsOverride = null,
        ?array $actedRequestIdsOverride = null,
        ?array $decidedByMeIdsOverride = null
    ): Builder
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $approvableIds = $approvableIdsOverride ?? $this->approvableRequestIds();
        $actedRequestIds = $actedRequestIdsOverride ?? $this->actedOnRequestIds();
        $decidedByMeIds = $decidedByMeIdsOverride ?? $this->decidedByMeRequestIds();
        $pendingIds = $this->scopeFilter === 'pending_my_approval' ? $approvableIds : null;

        // Analytics query must stay lean (no withCount/eager loads) to keep grouped SQL valid and fast.
        return SpendRequest::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('request_code', 'like', '%'.$this->search.'%')
                        ->orWhere('title', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn ($requesterQuery) => $requesterQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('metadata->type', $this->typeFilter))
            ->when($this->departmentFilter !== 'all', fn ($query) => $query->where('department_id', (int) $this->departmentFilter))
            ->when($this->dateFrom !== '', function ($query): void {
                $query->where(function ($dateQuery): void {
                    $dateQuery->whereDate('submitted_at', '>=', $this->dateFrom)
                        ->orWhere(function ($fallback): void {
                            $fallback->whereNull('submitted_at')
                                ->whereDate('created_at', '>=', $this->dateFrom);
                        });
                });
            })
            ->when($this->dateTo !== '', function ($query): void {
                $query->where(function ($dateQuery): void {
                    $dateQuery->whereDate('submitted_at', '<=', $this->dateTo)
                        ->orWhere(function ($fallback): void {
                            $fallback->whereNull('submitted_at')
                                ->whereDate('created_at', '<=', $this->dateTo);
                        });
                });
            })
            ->when($this->scopeFilter === 'mine', fn ($query) => $query->where('requested_by', (int) $user->id))
            ->when($this->scopeFilter === 'pending_my_approval', function ($query) use ($pendingIds): void {
                $query->whereIn('id', empty($pendingIds) ? [0] : $pendingIds);
            })
            ->when($this->scopeFilter === 'decided_by_me', function ($query) use ($decidedByMeIds): void {
                $query->whereIn('id', empty($decidedByMeIds) ? [0] : $decidedByMeIds);
            })
            ->when($this->scopeFilter === 'all', function ($query) use ($user, $approvableIds, $actedRequestIds): void {
                if ($this->canViewAllRequests($user)) {
                    return;
                }

                if ($user->role === UserRole::Manager->value) {
                    if (! $user->department_id) {
                        $query->whereIn('id', [0]);

                        return;
                    }

                    $query->where(function ($inner) use ($user, $approvableIds, $actedRequestIds): void {
                        $inner->where('department_id', (int) $user->department_id);
                        if (! empty($approvableIds)) {
                            $inner->orWhereIn('id', $approvableIds);
                        }
                        if (! empty($actedRequestIds)) {
                            $inner->orWhereIn('id', $actedRequestIds);
                        }
                    });

                    return;
                }

                $query->where(function ($inner) use ($user, $approvableIds, $actedRequestIds): void {
                    $inner->where('requested_by', (int) $user->id);
                    if (! empty($approvableIds)) {
                        $inner->orWhereIn('id', $approvableIds);
                    }
                    if (! empty($actedRequestIds)) {
                        $inner->orWhereIn('id', $actedRequestIds);
                    }
                });
            });
    }

    /**
     * @return array<int, int>
     */
    private function approvableRequestIds(): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user || ! $user->company_id) {
            return [];
        }
        $router = app(RequestApprovalRouter::class);

        return SpendRequest::query()
            ->where('status', 'in_review')
            ->get(['id', 'company_id', 'requested_by', 'department_id', 'workflow_id', 'current_approval_step', 'status'])
            ->filter(fn (SpendRequest $request): bool => $router->canApprove($user, $request))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function actedOnRequestIds(): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user || ! $user->company_id) {
            return [];
        }

        return RequestApproval::query()
            ->where('company_id', (int) $user->company_id)
            ->where('acted_by', (int) $user->id)
            ->pluck('request_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function decidedByMeRequestIds(): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user || ! $user->company_id) {
            return [];
        }

        return RequestApproval::query()
            ->where('company_id', (int) $user->company_id)
            ->where('acted_by', (int) $user->id)
            ->whereNotNull('action')
            ->pluck('request_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $approvableRequestIds
     * @param  array<int, string>  $requestTypeCodes
     * @return array{
     *   total_requests:int,
     *   total_amount:int,
     *   pending_my_action:int,
     *   status_counts:array<string,int>,
     *   type_counts:array<string,int>
     * }
     */
    private function buildRequestAnalytics(Builder $baseQuery, array $approvableRequestIds, array $requestTypeCodes): array
    {
        $totalRequests = (int) (clone $baseQuery)->count();
        $totalAmount = (int) ((clone $baseQuery)->sum('amount') ?? 0);
        $pendingMyAction = empty($approvableRequestIds)
            ? 0
            : (int) (clone $baseQuery)->whereIn('id', $approvableRequestIds)->count();

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $typeCounts = $this->typeBreakdown(clone $baseQuery, $requestTypeCodes);

        return [
            'total_requests' => $totalRequests,
            'total_amount' => $totalAmount,
            'pending_my_action' => $pendingMyAction,
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
        ];
    }

    /**
     * @param  array<int, string>  $requestTypeCodes
     * @return array<string, int>
     */
    private function typeBreakdown(Builder $baseQuery, array $requestTypeCodes): array
    {
        $driver = $baseQuery->getModel()->getConnection()->getDriverName();

        $raw = match ($driver) {
            'mysql', 'mariadb' => (clone $baseQuery)
                ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.type')), 'unknown') as type_key, COUNT(*) as total")
                ->groupBy('type_key')
                ->pluck('total', 'type_key')
                ->map(fn ($total): int => (int) $total)
                ->all(),
            'sqlite' => (clone $baseQuery)
                ->selectRaw("COALESCE(json_extract(metadata, '$.type'), 'unknown') as type_key, COUNT(*) as total")
                ->groupBy('type_key')
                ->pluck('total', 'type_key')
                ->map(fn ($total): int => (int) $total)
                ->all(),
            'pgsql' => (clone $baseQuery)
                ->selectRaw("COALESCE(metadata->>'type', 'unknown') as type_key, COUNT(*) as total")
                ->groupBy('type_key')
                ->pluck('total', 'type_key')
                ->map(fn ($total): int => (int) $total)
                ->all(),
            default => (clone $baseQuery)
                ->get(['metadata'])
                ->groupBy(fn (SpendRequest $request): string => (string) (($request->metadata['type'] ?? 'unknown')))
                ->map(fn ($group): int => $group->count())
                ->all(),
        };

        $counts = [];
        foreach ($requestTypeCodes as $code) {
            $counts[(string) $code] = (int) ($raw[(string) $code] ?? 0);
        }

        return $counts;
    }

    /**
     * @param  array<int, SpendRequest>  $pageRequests
     * @param  array<int, int>  $approvableRequestIds
     * @return array<int, array{can_approve: bool, text: string}>
     */
    private function buildRowApprovalContexts(array $pageRequests, array $approvableRequestIds): array
    {
        $router = app(RequestApprovalRouter::class);
        $context = [];

        foreach ($pageRequests as $request) {
            if ((string) $request->status !== 'in_review') {
                continue;
            }

            $requestId = (int) $request->id;
            $canApprove = in_array($requestId, $approvableRequestIds, true);

            if ($canApprove) {
                $context[$requestId] = [
                    'can_approve' => true,
                    'text' => 'Awaiting your decision',
                ];
                continue;
            }

            $step = $router->resolveCurrentStep($request);
            if (! $step) {
                $context[$requestId] = [
                    'can_approve' => false,
                    'text' => 'Awaiting configured approver',
                ];
                continue;
            }

            $eligibleApprovers = $router->resolveEligibleApprovers($step, $request)
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->all();
            $stepLabel = $this->workflowStepLabel($step);
            $contextText = $eligibleApprovers !== []
                ? sprintf('Awaiting %s: %s', $stepLabel, implode(', ', $eligibleApprovers))
                : sprintf('Awaiting %s', $stepLabel);

            $context[$requestId] = [
                'can_approve' => false,
                'text' => $contextText,
            ];
        }

        return $context;
    }

    private function findRequestOrFail(int $requestId): SpendRequest
    {
        /** @var SpendRequest $request */
        $request = SpendRequest::query()->findOrFail($requestId);

        return $request;
    }

    private function loadRequestForView(int $requestId): SpendRequest
    {
        return SpendRequest::query()
            ->with([
                'requester:id,name,email,avatar_path,gender,updated_at',
                'department:id,name',
                'vendor:id,name',
                'workflow:id,name',
                'items.vendor:id,name',
                'approvals' => fn ($query) => $query
                    ->with(['actor:id,name,avatar_path,gender,updated_at', 'workflowStep:id,step_order,step_key,actor_type,actor_value'])
                    ->orderBy('step_order'),
                'comments' => fn ($query) => $query
                    ->with('user:id,name,avatar_path,gender,updated_at')
                    ->orderBy('created_at'),
                'communicationLogs' => fn ($query) => $query
                    ->with('recipient:id,name,avatar_path,gender,updated_at')
                    ->latest('id'),
                'attachments' => fn ($query) => $query
                    ->with('uploader:id,name')
                    ->latest('uploaded_at'),
                'expenses' => fn ($query) => $query
                    ->with('creator:id,name')
                    ->latest('id'),
            ])
            ->findOrFail($requestId);
    }

    private function fillSelectedRequestData(SpendRequest $request): void
    {
        // Build one normalized view-model payload so Blade stays simple and state-safe.
        $request = $request->loadMissing([
            'requester:id,name,email,avatar_path,gender,updated_at',
            'department:id,name',
            'vendor:id,name',
            'workflow:id,name',
            'items.vendor:id,name',
            'approvals.actor:id,name,avatar_path,gender,updated_at',
            'approvals.workflowStep:id,step_order,step_key,actor_type,actor_value',
            'comments.user:id,name,avatar_path,gender,updated_at',
            'communicationLogs.recipient:id,name,avatar_path,gender,updated_at',
            'attachments.uploader:id,name',
            'expenses.creator:id,name',
        ]);

        $currentApprovers = [];
        $currentApproverProfiles = [];
        $currentStepLabel = null;
        $router = app(RequestApprovalRouter::class);
        if ((string) $request->status === 'in_review') {
            $step = $router->resolveCurrentStep($request);
            if ($step) {
                $currentStepLabel = $this->workflowStepLabel($step);
                $resolvedCurrentApprovers = $router->resolveEligibleApprovers($step, $request);
                $currentApprovers = $resolvedCurrentApprovers
                    ->pluck('name')
                    ->values()
                    ->all();
                $currentApproverProfiles = $resolvedCurrentApprovers
                    ->map(fn (User $user): array => $this->presentUser($user))
                    ->values()
                    ->all();
            }
        }

        $explicitApproverIds = $request->approvals
            ->filter(fn (RequestApproval $approval): bool => (string) $approval->workflowStep?->actor_type === 'user' && is_numeric((string) $approval->workflowStep?->actor_value))
            ->map(fn (RequestApproval $approval): int => (int) $approval->workflowStep->actor_value)
            ->unique()
            ->values();

        $explicitApproverProfiles = $explicitApproverIds->isEmpty()
            ? []
            : User::query()
                ->whereIn('id', $explicitApproverIds->all())
                ->get(['id', 'name', 'avatar_path', 'gender', 'updated_at'])
                ->mapWithKeys(fn (User $user): array => [(int) $user->id => $this->presentUser($user)])
                ->all();

        $explicitApproverNames = collect($explicitApproverProfiles)
            ->mapWithKeys(fn (array $profile, int $id): array => [$id => (string) ($profile['name'] ?? 'Assigned user')])
            ->all();
        $deliverySummaryByApproval = $request->communicationLogs
            ->filter(fn ($log): bool => ! empty($log->request_approval_id))
            ->groupBy(fn ($log): int => (int) $log->request_approval_id)
            ->map(function ($logs): array {
                $total = $logs->count();
                $sent = $logs->where('status', 'sent')->count();
                $queued = $logs->where('status', 'queued')->count();
                $failed = $logs->where('status', 'failed')->count();
                $skipped = $logs->where('status', 'skipped')->count();
                $channels = $logs
                    ->pluck('channel')
                    ->filter()
                    ->map(fn ($channel): string => strtoupper(str_replace('_', ' ', (string) $channel)))
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'total' => (int) $total,
                    'sent' => (int) $sent,
                    'queued' => (int) $queued,
                    'failed' => (int) $failed,
                    'skipped' => (int) $skipped,
                    'channels' => $channels,
                ];
            })
            ->all();
        $linkedExpense = $request->expenses->first();
        $canCreateExpense = Gate::allows('create', Expense::class)
            && (string) $request->status === 'approved'
            && ! $linkedExpense;
        $approvalContextMessage = null;
        if ((string) $request->status === 'in_review' && ! Gate::allows('approve', $request)) {
            $approvalContextMessage = ! empty($currentApprovers)
                ? 'Current approver(s) for this step: '.implode(', ', $currentApprovers).'.'
                : 'This step is assigned by workflow policy. You can view updates, but cannot act on this step.';
        }

        $this->selectedRequest = [
            'id' => $request->id,
            'request_code' => $request->request_code,
            'type' => (string) (($request->metadata ?? [])['type'] ?? 'spend'),
            'request_type_name' => (string) (($request->metadata ?? [])['request_type_name'] ?? ucfirst((string) (($request->metadata ?? [])['type'] ?? 'spend'))),
            'title' => $request->title,
            'description' => $request->description ?: '-',
            'amount' => (int) $request->amount,
            'currency' => strtoupper((string) $request->currency),
            'status' => (string) $request->status,
            'requester' => $request->requester?->name ?? '-',
            'requester_profile' => $this->presentUser($request->requester, '-'),
            'department' => $request->department?->name ?? '-',
            'vendor' => $request->vendor?->name ?? 'Unlinked',
            'workflow' => $request->workflow?->name ?? 'Auto Default',
            'needed_by' => ($request->metadata ?? [])['needed_by'] ?? null,
            'start_date' => ($request->metadata ?? [])['start_date'] ?? null,
            'end_date' => ($request->metadata ?? [])['end_date'] ?? null,
            'destination' => ($request->metadata ?? [])['destination'] ?? null,
            'leave_type' => ($request->metadata ?? [])['leave_type'] ?? null,
            'notification_channels' => array_values((array) (($request->metadata ?? [])['notification_channels'] ?? [])),
            'submitted_at' => optional($request->submitted_at)->format('M d, Y H:i'),
            'decided_at' => optional($request->decided_at)->format('M d, Y H:i'),
            'decision_note' => $request->decision_note,
            'current_approval_step' => $request->current_approval_step,
            'current_step_label' => $currentStepLabel,
            'current_approvers' => $currentApprovers,
            'current_approver_profiles' => $currentApproverProfiles,
            'approval_context_message' => $approvalContextMessage,
            'can_submit' => Gate::allows('submit', $request),
            'can_approve' => Gate::allows('approve', $request),
            'can_update' => Gate::allows('update', $request),
            'can_upload_attachments' => Gate::allows('uploadAttachment', $request),
            'can_comment' => Gate::allows('view', $request),
            'can_create_expense' => $canCreateExpense,
            'linked_expense' => $linkedExpense ? [
                'id' => (int) $linkedExpense->id,
                'expense_code' => (string) $linkedExpense->expense_code,
                'status' => (string) $linkedExpense->status,
                'amount' => (int) $linkedExpense->amount,
                'currency' => strtoupper((string) $request->currency),
                'expense_date' => optional($linkedExpense->expense_date)->format('M d, Y'),
                'created_by' => (string) ($linkedExpense->creator?->name ?? 'System'),
            ] : null,
            'items' => $request->items
                ->map(fn ($item): array => [
                    'name' => $item->item_name,
                    'quantity' => (int) $item->quantity,
                    'unit_cost' => (int) $item->unit_cost,
                    'line_total' => (int) $item->line_total,
                    'vendor' => $item->vendor?->name ?? '-',
                    'category' => $item->category ?: '-',
                    'description' => $item->description ?: '-',
                ])
                ->values()
                ->all(),
            'comments' => $request->comments
                ->map(fn (RequestComment $comment): array => [
                    'id' => (int) $comment->id,
                    'is_mine' => (int) $comment->user_id === (int) \Illuminate\Support\Facades\Auth::id(),
                    'author' => $comment->user?->name ?? 'Unknown',
                    'author_profile' => $this->presentUser($comment->user, 'Unknown'),
                    'body' => (string) $comment->body,
                    'created_at' => optional($comment->created_at)->format('M d, Y H:i'),
                ])
                ->values()
                ->all(),
            'attachments' => $request->attachments
                ->map(fn (RequestAttachment $attachment): array => [
                    'id' => (int) $attachment->id,
                    'original_name' => (string) $attachment->original_name,
                    'mime_type' => strtoupper((string) $attachment->mime_type),
                    'file_size_kb' => number_format(((int) $attachment->file_size) / 1024, 1),
                    'uploaded_at' => optional($attachment->uploaded_at)->format('M d, Y H:i'),
                    'uploaded_by' => (string) ($attachment->uploader?->name ?? 'Unknown'),
                ])
                ->values()
                ->all(),
            'policy_warnings' => array_values(array_filter(array_map(
                'strval',
                (array) (($request->metadata ?? [])['policy_warnings'] ?? [])
            ))),
            'policy_checks' => (array) (($request->metadata ?? [])['policy_checks'] ?? []),
            'communication_logs' => $request->communicationLogs
                ->map(fn ($log): array => [
                    'event' => ucwords(str_replace('_', ' ', (string) $log->event)),
                    'event_key' => (string) $log->event,
                    'channel' => strtoupper(str_replace('_', ' ', (string) $log->channel)),
                    'channel_key' => (string) $log->channel,
                    'status' => ucfirst(str_replace('_', ' ', (string) $log->status)),
                    'status_key' => (string) $log->status,
                    'recipient' => $log->recipient?->name ?? 'Workflow audience',
                    'recipient_profile' => $log->recipient
                        ? $this->presentUser($log->recipient)
                        : $this->presentVirtualPerson('Workflow audience'),
                    'created_at' => optional($log->created_at)->format('M d, Y H:i'),
                    'read_at' => optional($log->read_at)->format('M d, Y H:i'),
                ])
                ->values()
                ->all(),
            'timeline' => $request->approvals
                ->sortBy('step_order')
                ->map(fn (RequestApproval $approval): array => [
                    'request_approval_id' => (int) $approval->id,
                    'step_order' => (int) $approval->step_order,
                    'step_label' => $this->approvalStepLabel($approval),
                    'status' => (string) $approval->status,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $approval->status)),
                    'decision' => $approval->action ? ucfirst((string) $approval->action) : 'Awaiting decision',
                    'approver' => $this->approvalApproverLabel(
                        $approval,
                        $explicitApproverNames,
                        $currentApprovers
                    ),
                    'approver_profile' => $this->approvalApproverProfile(
                        $approval,
                        $explicitApproverProfiles,
                        $currentApproverProfiles
                    ),
                    'comment' => $approval->comment,
                    'acted_at' => optional($approval->acted_at)->format('M d, Y H:i') ?: 'Awaiting decision',
                    'due_at' => optional($approval->due_at)->format('M d, Y H:i'),
                    'reminder_sent_at' => optional($approval->reminder_sent_at)->format('M d, Y H:i'),
                    'escalated_at' => optional($approval->escalated_at)->format('M d, Y H:i'),
                    'is_overdue' => (string) $approval->status === 'pending'
                        && ! $approval->acted_at
                        && $approval->due_at
                        && now()->greaterThan($approval->due_at),
                    'delivery_summary' => $deliverySummaryByApproval[(int) $approval->id] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function prepareSubmitChannels(SpendRequest $request): void
    {
        $this->submitChannelPolicies = $this->channelPolicies();
        $selectableChannels = array_keys(array_filter(
            $this->submitChannelPolicies,
            fn (array $policy): bool => (bool) ($policy['selectable'] ?? false)
        ));

        $workflowChannels = $this->resolveWorkflowChannels($request, $selectableChannels);
        $requestChannels = array_values(array_unique(array_map(
            'strval',
            (array) (($request->metadata ?? [])['notification_channels'] ?? [])
        )));

        $preselected = $requestChannels !== [] ? $requestChannels : $workflowChannels;
        if ($preselected === []) {
            $preselected = $selectableChannels;
        }

        $this->submitNotificationChannels = array_values(array_intersect($preselected, $selectableChannels));
    }

    private function prepareDecisionChannels(SpendRequest $request): void
    {
        $this->decisionChannelPolicies = $this->channelPolicies();
        $selectableChannels = array_keys(array_filter(
            $this->decisionChannelPolicies,
            fn (array $policy): bool => (bool) ($policy['selectable'] ?? false)
        ));

        $stepChannels = $this->resolveCurrentStepChannels($request, $selectableChannels);
        $this->decisionNotificationChannels = array_values(array_intersect($stepChannels, $selectableChannels));
    }

    /**
     * @param  array<int, string>  $organizationChannels
     * @return array<int, string>
     */
    private function resolveCurrentStepChannels(SpendRequest $request, array $organizationChannels): array
    {
        if ($organizationChannels === [] || (string) $request->status !== 'in_review') {
            return [];
        }

        $currentStepOrder = (int) ($request->current_approval_step ?? 0);
        if ($currentStepOrder < 1) {
            return $organizationChannels;
        }

        $approvalRow = $request->approvals
            ->firstWhere('step_order', $currentStepOrder);
        $rowChannels = array_values(array_unique(array_map(
            'strval',
            (array) (($approvalRow?->metadata ?? [])['notification_channels'] ?? [])
        )));

        if ($rowChannels !== []) {
            $rowChannels = array_values(array_intersect($rowChannels, $organizationChannels));
            if ($rowChannels !== []) {
                return $rowChannels;
            }
        }

        $step = app(RequestApprovalRouter::class)->resolveCurrentStep($request);
        if (! $step) {
            return $organizationChannels;
        }

        $stepChannels = array_values(array_unique(array_map(
            'strval',
            (array) ($step->notification_channels ?? [])
        )));
        if ($stepChannels === []) {
            return $organizationChannels;
        }

        $stepChannels = array_values(array_intersect($stepChannels, $organizationChannels));

        return $stepChannels === [] ? $organizationChannels : $stepChannels;
    }

    /**
     * @param  array<int, string>  $organizationChannels
     * @return array<int, string>
     */
    private function resolveWorkflowChannels(SpendRequest $request, array $organizationChannels): array
    {
        if ($organizationChannels === []) {
            return [];
        }

        $router = app(RequestApprovalRouter::class);
        $workflow = $router->resolveActiveWorkflow($request);
        if (! $workflow) {
            return $organizationChannels;
        }

        $steps = ApprovalWorkflowStep::query()
            ->where('company_id', (int) $request->company_id)
            ->where('workflow_id', (int) $workflow->id)
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get(['notification_channels']);

        if ($steps->isEmpty()) {
            return $organizationChannels;
        }

        $channels = [];
        foreach ($steps as $step) {
            $stepChannels = array_values(array_intersect(
                array_values(array_unique(array_map('strval', (array) ($step->notification_channels ?? [])))),
                $organizationChannels
            ));

            if ($stepChannels === []) {
                $stepChannels = $organizationChannels;
            }

            foreach ($stepChannels as $channel) {
                if (! in_array($channel, $channels, true)) {
                    $channels[] = $channel;
                }
            }
        }

        return $channels === [] ? $organizationChannels : $channels;
    }

    private function attachDraftUploadedFiles(
        UploadRequestAttachment $uploadRequestAttachment,
        SpendRequest $request
    ): void {
        if (empty($this->newAttachments)) {
            return;
        }

        foreach ($this->newAttachments as $file) {
            if ($file) {
                $uploadRequestAttachment(\Illuminate\Support\Facades\Auth::user(), $request, $file);
            }
        }

        $this->newAttachments = [];
    }

    public function requestAttachmentDownloadUrlById(int $attachmentId): string
    {
        return route('requests.attachments.download', ['attachment' => $attachmentId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        $departmentId = $this->currentUserDepartmentId();
        $items = $this->currentTypeRequiresLineItems()
            ? collect($this->lineItems)->map(fn (array $item): array => [
                'name' => trim((string) $item['name']),
                'description' => $this->nullableString($item['description'] ?? null),
                'quantity' => (int) ($item['quantity'] === '' ? 0 : $item['quantity']),
                'unit_cost' => (int) ($item['unit_cost'] === '' ? 0 : $item['unit_cost']),
                'vendor_id' => $item['vendor_id'] !== '' ? (int) $item['vendor_id'] : null,
                'category' => $this->nullableString($item['category'] ?? null),
            ])->values()->all()
            : [];

        return [
            'type' => $this->form['type'],
            'title' => trim((string) $this->form['title']),
            'description' => $this->nullableString($this->form['description']),
            'department_id' => $departmentId ? (int) $departmentId : null,
            'vendor_id' => $this->form['vendor_id'] !== '' ? (int) $this->form['vendor_id'] : null,
            'workflow_id' => $this->form['workflow_id'] !== '' ? (int) $this->form['workflow_id'] : null,
            'amount' => $this->form['amount'] !== '' ? (int) $this->form['amount'] : null,
            'needed_by' => $this->form['needed_by'] !== '' ? $this->form['needed_by'] : null,
            'start_date' => $this->form['start_date'] !== '' ? $this->form['start_date'] : null,
            'end_date' => $this->form['end_date'] !== '' ? $this->form['end_date'] : null,
            'destination' => $this->nullableString($this->form['destination']),
            'leave_type' => $this->nullableString($this->form['leave_type']),
            'handover_user_id' => $this->form['handover_user_id'] !== '' ? (int) $this->form['handover_user_id'] : null,
            'items' => $items,
        ];
    }

    private function resetForm(): void
    {
        $this->refreshRequestTypeMap();

        $this->form = [
            'type' => '',
            'title' => '',
            'description' => '',
            'department_id' => $this->currentUserDepartmentId() ? (string) $this->currentUserDepartmentId() : '',
            'vendor_id' => '',
            'workflow_id' => '',
            'currency' => $this->companyCurrency(),
            'amount' => '',
            'needed_by' => '',
            'start_date' => '',
            'end_date' => '',
            'destination' => '',
            'leave_type' => '',
            'handover_user_id' => '',
        ];

        $this->lineItems = [];
        $this->addLineItem();
        $this->newAttachments = [];
    }

    private function fillFormFromRequest(SpendRequest $request): void
    {
        $request->loadMissing('items');

        $this->form = [
            'type' => (string) (($request->metadata ?? [])['type'] ?? ''),
            'title' => (string) $request->title,
            'description' => (string) ($request->description ?? ''),
            'department_id' => $this->currentUserDepartmentId() ? (string) $this->currentUserDepartmentId() : '',
            'vendor_id' => $request->vendor_id ? (string) $request->vendor_id : '',
            'workflow_id' => $request->workflow_id ? (string) $request->workflow_id : '',
            'currency' => strtoupper((string) ($request->currency ?: $this->companyCurrency())),
            'amount' => (string) ((int) $request->amount),
            'needed_by' => (string) (($request->metadata ?? [])['needed_by'] ?? ''),
            'start_date' => (string) (($request->metadata ?? [])['start_date'] ?? ''),
            'end_date' => (string) (($request->metadata ?? [])['end_date'] ?? ''),
            'destination' => (string) (($request->metadata ?? [])['destination'] ?? ''),
            'leave_type' => (string) (($request->metadata ?? [])['leave_type'] ?? ''),
            'handover_user_id' => ! empty(($request->metadata ?? [])['handover_user_id']) ? (string) (($request->metadata ?? [])['handover_user_id']) : '',
        ];

        $this->lineItems = $request->items
            ->map(fn ($item): array => [
                'name' => (string) $item->item_name,
                'description' => (string) ($item->description ?? ''),
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'vendor_id' => $item->vendor_id ? (string) $item->vendor_id : '',
                'category' => (string) ($item->category ?? ''),
            ])
            ->values()
            ->all();

        if (empty($this->lineItems)) {
            $this->addLineItem();
        }
    }

    private function refreshRequestTypeMap(): void
    {
        $this->requestTypeMap = CompanyRequestType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'name',
                'code',
                'requires_amount',
                'requires_line_items',
                'requires_date_range',
                'requires_vendor',
                'requires_attachments',
            ])
            ->mapWithKeys(fn (CompanyRequestType $type): array => [
                (string) $type->code => [
                    'name' => (string) $type->name,
                    'code' => (string) $type->code,
                    'requires_amount' => (bool) $type->requires_amount,
                    'requires_line_items' => (bool) $type->requires_line_items,
                    'requires_date_range' => (bool) $type->requires_date_range,
                    'requires_vendor' => (bool) $type->requires_vendor,
                    'requires_attachments' => (bool) $type->requires_attachments,
                ],
            ])->all();
    }

    private function currentTypeRequiresLineItems(): bool
    {
        $typeCode = (string) ($this->form['type'] ?? '');
        $type = $this->requestTypeMap[$typeCode] ?? null;

        return (bool) ($type['requires_line_items'] ?? false);
    }

    /**
     * @return array<string, array{label: string, enabled: bool, configured: bool, selectable: bool}>
     */
    private function channelPolicies(): array
    {
        $settings = CompanyCommunicationSetting::query()
            ->firstOrCreate(
                ['company_id' => (int) \Illuminate\Support\Facades\Auth::user()->company_id],
                CompanyCommunicationSetting::defaultAttributes()
            );

        return [
            CompanyCommunicationSetting::CHANNEL_IN_APP => [
                'label' => 'In-app',
                'enabled' => (bool) $settings->in_app_enabled,
                'configured' => true,
                'selectable' => (bool) $settings->in_app_enabled,
            ],
            CompanyCommunicationSetting::CHANNEL_EMAIL => [
                'label' => 'Email',
                'enabled' => (bool) $settings->email_enabled,
                'configured' => (bool) $settings->email_configured,
                'selectable' => (bool) $settings->email_enabled && (bool) $settings->email_configured,
            ],
            CompanyCommunicationSetting::CHANNEL_SMS => [
                'label' => 'SMS',
                'enabled' => (bool) $settings->sms_enabled,
                'configured' => (bool) $settings->sms_configured,
                'selectable' => (bool) $settings->sms_enabled && (bool) $settings->sms_configured,
            ],
        ];
    }

    private function canViewAllRequests(User $user): bool
    {
        return in_array(
            (string) $user->role,
            [UserRole::Owner->value, UserRole::Finance->value, UserRole::Auditor->value],
            true
        );
    }

    private function workflowStepLabel(ApprovalWorkflowStep $step): string
    {
        if ($step->step_key) {
            return ucwords(str_replace(['_', '-'], ' ', (string) $step->step_key));
        }

        return match ((string) $step->actor_type) {
            'reports_to' => 'Direct Manager Approval',
            'department_manager' => 'Department Head Approval',
            'role' => ucwords(str_replace('_', ' ', (string) $step->actor_value)).' Role Approval',
            'user' => 'Assigned Approver',
            default => 'Step '.$step->step_order,
        };
    }

    private function approvalStepLabel(RequestApproval $approval): string
    {
        if ($approval->step_key) {
            return ucwords(str_replace(['_', '-'], ' ', (string) $approval->step_key));
        }

        if ($approval->workflowStep) {
            return $this->workflowStepLabel($approval->workflowStep);
        }

        return 'Step '.$approval->step_order;
    }

    /**
     * @param  array<int, string>  $explicitApproverNames
     * @param  array<int, string>  $currentApprovers
     */
    private function approvalApproverLabel(
        RequestApproval $approval,
        array $explicitApproverNames,
        array $currentApprovers
    ): string {
        if ($approval->actor?->name) {
            return (string) $approval->actor->name;
        }

        $step = $approval->workflowStep;
        if (! $step) {
            return 'Not assigned';
        }

        return match ((string) $step->actor_type) {
            'reports_to' => ! empty($currentApprovers) ? 'Direct manager ('.implode(', ', $currentApprovers).')' : 'Direct manager',
            'department_manager' => 'Department head',
            'role' => ucwords(str_replace('_', ' ', (string) $step->actor_value)).' role',
            'user' => $explicitApproverNames[(int) $step->actor_value] ?? 'Assigned user',
            default => ! empty($currentApprovers) ? implode(', ', $currentApprovers) : 'Not assigned',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $explicitApproverProfiles
     * @param  array<int, array<string, mixed>>  $currentApproverProfiles
     * @return array<string, mixed>|null
     */
    private function approvalApproverProfile(
        RequestApproval $approval,
        array $explicitApproverProfiles,
        array $currentApproverProfiles
    ): ?array {
        if ($approval->actor) {
            return $this->presentUser($approval->actor);
        }

        $step = $approval->workflowStep;
        if (! $step) {
            return null;
        }

        return match ((string) $step->actor_type) {
            'user' => $explicitApproverProfiles[(int) $step->actor_value] ?? null,
            'reports_to', 'department_manager' => count($currentApproverProfiles) === 1
                ? $currentApproverProfiles[0]
                : null,
            default => null,
        };
    }

    /**
     * @return array{name: string, avatar_url: string|null, initials: string, avatar_bg: string, avatar_border: string, avatar_text: string}
     */
    private function presentUser(?User $user, string $fallbackName = 'Unknown'): array
    {
        $name = $user?->name ? (string) $user->name : $fallbackName;
        $gender = strtolower((string) ($user?->gender ?? 'other'));
        $palette = $this->avatarPalette($gender);
        $avatarUrl = $user && $user->avatar_path
            ? route('users.avatar', ['user' => $user->id, 'v' => optional($user->updated_at)->timestamp])
            : null;

        return [
            'name' => $name,
            'avatar_url' => $avatarUrl,
            'initials' => $this->initialsFromName($name),
            'avatar_bg' => $palette['bg'],
            'avatar_border' => $palette['border'],
            'avatar_text' => $palette['text'],
        ];
    }

    /**
     * @return array{name: string, avatar_url: string|null, initials: string, avatar_bg: string, avatar_border: string, avatar_text: string}
     */
    private function presentVirtualPerson(string $name): array
    {
        $palette = $this->avatarPalette('other');

        return [
            'name' => $name,
            'avatar_url' => null,
            'initials' => $this->initialsFromName($name),
            'avatar_bg' => $palette['bg'],
            'avatar_border' => $palette['border'],
            'avatar_text' => $palette['text'],
        ];
    }

    /**
     * @return array{bg: string, border: string, text: string}
     */
    private function avatarPalette(string $gender): array
    {
        if ($gender === 'male') {
            return [
                'bg' => '#dbeafe',
                'border' => '#93c5fd',
                'text' => '#1e3a8a',
            ];
        }

        if ($gender === 'female') {
            return [
                'bg' => '#fce7f3',
                'border' => '#f9a8d4',
                'text' => '#831843',
            ];
        }

        return [
            'bg' => '#ede9fe',
            'border' => '#c4b5fd',
            'text' => '#4c1d95',
        ];
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
        $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';
        $initials = $first.$second;

        return $initials !== '' ? $initials : '?';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function companyCurrency(): string
    {
        return strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN'));
    }

    private function currentUserDepartmentId(): ?int
    {
        $departmentId = \Illuminate\Support\Facades\Auth::user()?->department_id;

        return $departmentId ? (int) $departmentId : null;
    }

    private function currentUserDepartmentName(): string
    {
        $user = \Illuminate\Support\Facades\Auth::user()?->loadMissing('department:id,name');

        return $user?->department?->name ?? 'Not assigned';
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackWarning = null;
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackWarning(string $message): void
    {
        $this->feedbackWarning = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackWarning = null;
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }

    /**
     * @return array<int, string>
     */
    private function policyWarningsFromRequest(SpendRequest $request): array
    {
        $metadata = (array) ($request->metadata ?? []);

        return array_values(array_filter(array_map(
            'strval',
            (array) ($metadata['policy_warnings'] ?? [])
        )));
    }

    private function markInAppNotificationsAsRead(int $requestId): void
    {
        $userId = (int) \Illuminate\Support\Facades\Auth::id();
        if ($requestId < 1 || $userId < 1) {
            return;
        }

        RequestCommunicationLog::query()
            ->where('request_id', $requestId)
            ->where('recipient_user_id', $userId)
            ->where('channel', CompanyCommunicationSetting::CHANNEL_IN_APP)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = [
            'type',
            'title',
            'description',
            'department_id',
            'vendor_id',
            'workflow_id',
            'currency',
            'amount',
            'needed_by',
            'start_date',
            'end_date',
            'destination',
            'leave_type',
            'handover_user_id',
            'items',
        ];

        foreach ($errors as $key => $messages) {
            if ($key === 'no_changes') {
                $mapped['form.no_changes'] = $messages;
                continue;
            }

            if ($key === 'comment') {
                $mapped['decisionComment'] = $messages;
                continue;
            }

            if ($key === 'body') {
                $mapped['threadComment'] = $messages;
                continue;
            }

            if ($key === 'file' || $key === 'attachments') {
                $mapped['newAttachments'] = $messages;
                $mapped['viewNewAttachments'] = $messages;
                continue;
            }

            if (in_array($key, ['amount', 'workflow', 'status', 'approver', 'duplicate_override'], true)) {
                $mapped['submitPolicy'] = $messages;
                continue;
            }

            if (str_starts_with($key, 'newAttachments.')) {
                $mapped[$key] = $messages;
                continue;
            }

            if (str_starts_with($key, 'viewNewAttachments.')) {
                $mapped[$key] = $messages;
                continue;
            }

            if ($key === 'notification_channels' || str_starts_with($key, 'notification_channels.')) {
                $mapped['submitNotificationChannels'] = $messages;
                continue;
            }

            if ($key === 'submitNotificationChannels' || str_starts_with($key, 'submitNotificationChannels.')) {
                $mapped['submitNotificationChannels'] = $messages;
                continue;
            }

            if ($key === 'decisionNotificationChannels' || str_starts_with($key, 'decisionNotificationChannels.')) {
                $mapped['decisionNotificationChannels'] = $messages;
                continue;
            }

            if (str_starts_with($key, 'form.') || str_starts_with($key, 'lineItems.') || str_starts_with($key, 'items.')) {
                if (str_starts_with($key, 'items.')) {
                    $mapped['lineItems.'.substr($key, 6)] = $messages;
                } else {
                    $mapped[$key] = $messages;
                }
                continue;
            }

            if (in_array($key, $formFields, true)) {
                $mapped['form.'.$key] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }
}
