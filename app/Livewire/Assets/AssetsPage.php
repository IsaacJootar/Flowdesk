<?php

namespace App\Livewire\Assets;

use App\Actions\Assets\AssignAsset;
use App\Actions\Assets\CreateAsset;
use App\Actions\Assets\CreateAssetCategory;
use App\Actions\Assets\DisposeAsset;
use App\Actions\Assets\RecordAssetMaintenance;
use App\Actions\Assets\ReturnAsset;
use App\Actions\Assets\UpdateAsset;
use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCategory;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Company\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class AssetsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $categoryFilter = 'all';

    public string $assignmentFilter = 'all';

    public int $perPage = 10;

    public bool $showAssetModal = false;

    public bool $showCategoryModal = false;

    public bool $showAssignmentModal = false;

    public bool $showReturnModal = false;

    public bool $showMaintenanceModal = false;

    public bool $showDisposalModal = false;

    public bool $showHistoryModal = false;

    public bool $showBulkActionModal = false;

    public bool $isEditingAsset = false;

    public ?int $editingAssetId = null;

    public ?int $selectedAssetId = null;

    public ?int $assignmentAssetId = null;

    public ?int $returnAssetId = null;

    public ?int $maintenanceAssetId = null;

    public ?int $disposalAssetId = null;

    public string $bulkActionType = '';

    /** @var array<int> */
    public array $selectedAssetIds = [];

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array<string, mixed> */
    public array $assetForm = [
        'asset_category_id' => '',
        'name' => '',
        'serial_number' => '',
        'acquisition_date' => '',
        'purchase_amount' => '',
        'currency' => '',
        'condition' => 'good',
        'notes' => '',
        'maintenance_due_date' => '',
        'warranty_expires_at' => '',
    ];

    /** @var array<string, mixed> */
    public array $categoryForm = [
        'name' => '',
        'description' => '',
        'is_active' => true,
    ];

    /** @var array<string, mixed> */
    public array $assignmentForm = [
        'target_user_id' => '',
        'target_department_id' => '',
        'event_date' => '',
        'summary' => '',
        'details' => '',
    ];

    /** @var array<string, mixed> */
    public array $maintenanceForm = [
        'event_date' => '',
        'summary' => '',
        'amount' => '',
        'currency' => '',
        'details' => '',
    ];

    /** @var array<string, mixed> */
    public array $disposalForm = [
        'event_date' => '',
        'summary' => '',
        'salvage_amount' => '',
        'details' => '',
    ];

    /** @var array<string, mixed> */
    public array $returnForm = [
        'event_date' => '',
        'summary' => '',
        'details' => '',
    ];

    /** @var array<string, mixed> */
    public array $bulkForm = [
        'target_user_id' => '',
        'target_department_id' => '',
        'event_date' => '',
        'summary' => '',
        'details' => '',
        'salvage_amount' => '',
    ];

    /**
     * @throws AuthorizationException
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', Asset::class);

        $currency = strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN'));
        $today = now()->toDateString();
        $nowWithTime = now()->format('Y-m-d\\TH:i');

        $this->assetForm['currency'] = $currency;
        $this->assignmentForm['event_date'] = $nowWithTime;
        $this->maintenanceForm['event_date'] = $today;
        $this->maintenanceForm['currency'] = $currency;
        $this->disposalForm['event_date'] = $today;
        $this->returnForm['event_date'] = $today;
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->selectedAssetIds = [];
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->selectedAssetIds = [];
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->selectedAssetIds = [];
        $this->resetPage();
    }

    public function updatedAssignmentFilter(): void
    {
        $this->selectedAssetIds = [];
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function updatedSelectedAssetIds(): void
    {
        $this->selectedAssetIds = array_values(array_unique(array_filter(
            array_map('intval', $this->selectedAssetIds),
            fn (int $id): bool => $id > 0
        )));
    }

    public function openCreateAssetModal(): void
    {
        Gate::authorize('create', Asset::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->resetAssetForm();
        $this->isEditingAsset = false;
        $this->editingAssetId = null;
        $this->showAssetModal = true;
    }

    public function openEditAssetModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('update', $asset);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->isEditingAsset = true;
        $this->editingAssetId = $asset->id;
        $this->fillAssetForm($asset);
        $this->showAssetModal = true;
    }

    public function closeAssetModal(): void
    {
        $this->showAssetModal = false;
        $this->isEditingAsset = false;
        $this->editingAssetId = null;
        $this->resetAssetForm();
        $this->resetValidation();
    }

    public function saveAsset(CreateAsset $createAsset, UpdateAsset $updateAsset): void
    {
        $this->feedbackError = null;

        try {
            if ($this->isEditingAsset && $this->editingAssetId) {
                $asset = $this->findAssetOrFail($this->editingAssetId);
                $updateAsset(\Illuminate\Support\Facades\Auth::user(), $asset, $this->assetPayload());
                $this->setFeedback('Asset updated successfully.');
            } else {
                $createAsset(\Illuminate\Support\Facades\Auth::user(), $this->assetPayload());
                $this->setFeedback('Asset registered successfully.');
            }
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            if (array_key_exists('no_changes', $errors)) {
                $this->addError('assetForm.no_changes', (string) ($errors['no_changes'][0] ?? 'No changes made.'));

                return;
            }

            throw ValidationException::withMessages($errors);
        } catch (QueryException $exception) {
            report($exception);
            $this->setFeedbackError('Unable to save asset right now. Run migrations and retry.');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to save asset right now.');

            return;
        }

        $this->closeAssetModal();
        $this->resetPage();
    }

    public function openCategoryModal(): void
    {
        Gate::authorize('create', Asset::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->resetCategoryForm();
        $this->showCategoryModal = true;
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->resetCategoryForm();
        $this->resetValidation();
    }

    public function saveCategory(CreateAssetCategory $createAssetCategory): void
    {
        $this->feedbackError = null;
        try {
            $category = $createAssetCategory(\Illuminate\Support\Facades\Auth::user(), $this->categoryPayload());
        } catch (Throwable $throwable) {
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }
            $this->setFeedbackError('Unable to create category right now.');

            return;
        }

        $this->setFeedback('Asset category created.');
        $this->assetForm['asset_category_id'] = (string) $category->id;
        $this->closeCategoryModal();
    }

    public function openAssignmentModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('assign', $asset);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->assignmentAssetId = $asset->id;
        $this->resetAssignmentForm();
        $this->assignmentForm['target_user_id'] = (string) ($asset->assigned_to_user_id ?? '');
        $this->syncAssignmentDepartmentFromAssignee();
        $this->assignmentForm['summary'] = $asset->assigned_to_user_id ? 'Asset transferred' : 'Asset assigned';
        $this->showAssignmentModal = true;
    }

    public function closeAssignmentModal(): void
    {
        $this->showAssignmentModal = false;
        $this->assignmentAssetId = null;
        $this->resetAssignmentForm();
        $this->resetValidation();
    }

    public function openReturnModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('assign', $asset);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->returnAssetId = $asset->id;
        $this->resetReturnForm();
        $this->returnForm['summary'] = 'Asset returned to inventory';
        $this->showReturnModal = true;
    }

    public function closeReturnModal(): void
    {
        $this->showReturnModal = false;
        $this->returnAssetId = null;
        $this->resetReturnForm();
        $this->resetValidation();
    }

    public function saveReturn(ReturnAsset $returnAsset): void
    {
        if (! $this->returnAssetId) {
            return;
        }

        $this->feedbackError = null;
        $asset = $this->findAssetOrFail($this->returnAssetId);

        try {
            $returnAsset(\Illuminate\Support\Facades\Auth::user(), $asset, $this->returnPayload());
            $this->setFeedback('Asset returned to inventory.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }
            $this->setFeedbackError('Unable to return asset right now.');

            return;
        }

        $this->closeReturnModal();
        $this->refreshHistoryIfSelected($asset->id);
    }

    public function saveAssignment(AssignAsset $assignAsset): void
    {
        if (! $this->assignmentAssetId) {
            return;
        }

        $this->feedbackError = null;
        $asset = $this->findAssetOrFail($this->assignmentAssetId);

        try {
            $assignAsset(\Illuminate\Support\Facades\Auth::user(), $asset, $this->assignmentPayload());
            $this->setFeedback('Asset custody updated.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }
            $this->setFeedbackError('Unable to update assignment right now.');

            return;
        }

        $this->closeAssignmentModal();
        $this->refreshHistoryIfSelected($asset->id);
    }

    public function openMaintenanceModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('logMaintenance', $asset);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->maintenanceAssetId = $asset->id;
        $this->resetMaintenanceForm();
        $this->maintenanceForm['summary'] = 'Scheduled maintenance';
        $this->showMaintenanceModal = true;
    }

    public function closeMaintenanceModal(): void
    {
        $this->showMaintenanceModal = false;
        $this->maintenanceAssetId = null;
        $this->resetMaintenanceForm();
        $this->resetValidation();
    }

    public function saveMaintenance(RecordAssetMaintenance $recordAssetMaintenance): void
    {
        if (! $this->maintenanceAssetId) {
            return;
        }

        $this->feedbackError = null;
        $asset = $this->findAssetOrFail($this->maintenanceAssetId);

        try {
            $recordAssetMaintenance(\Illuminate\Support\Facades\Auth::user(), $asset, $this->maintenancePayload());
            $this->setFeedback('Maintenance event logged.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }
            $this->setFeedbackError('Unable to log maintenance right now.');

            return;
        }

        $this->closeMaintenanceModal();
        $this->refreshHistoryIfSelected($asset->id);
    }

    public function openDisposalModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('dispose', $asset);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->disposalAssetId = $asset->id;
        $this->resetDisposalForm();
        $this->disposalForm['summary'] = 'Asset disposed';
        $this->showDisposalModal = true;
    }

    public function closeDisposalModal(): void
    {
        $this->showDisposalModal = false;
        $this->disposalAssetId = null;
        $this->resetDisposalForm();
        $this->resetValidation();
    }

    public function saveDisposal(DisposeAsset $disposeAsset): void
    {
        if (! $this->disposalAssetId) {
            return;
        }

        $this->feedbackError = null;
        $asset = $this->findAssetOrFail($this->disposalAssetId);

        try {
            $disposeAsset(\Illuminate\Support\Facades\Auth::user(), $asset, $this->disposalPayload());
            $this->setFeedback('Asset marked as disposed.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof ValidationException) {
                throw $throwable;
            }
            $this->setFeedbackError('Unable to dispose asset right now.');

            return;
        }

        $this->closeDisposalModal();
        $this->refreshHistoryIfSelected($asset->id);
    }

    public function toggleAssetSelection(int $assetId): void
    {
        $normalized = (int) $assetId;
        if ($normalized <= 0) {
            return;
        }

        if (in_array($normalized, $this->selectedAssetIds, true)) {
            $this->selectedAssetIds = array_values(array_filter(
                $this->selectedAssetIds,
                fn (int $id): bool => $id !== $normalized
            ));

            return;
        }

        $this->selectedAssetIds[] = $normalized;
        $this->selectedAssetIds = array_values(array_unique(array_map('intval', $this->selectedAssetIds)));
    }

    public function toggleSelectVisibleAssets(): void
    {
        $visibleIds = $this->visibleAssetIds;
        if ($visibleIds === []) {
            return;
        }

        $allVisibleSelected = count(array_diff($visibleIds, $this->selectedAssetIds)) === 0;
        if ($allVisibleSelected) {
            $this->selectedAssetIds = [];

            return;
        }

        $this->selectedAssetIds = array_values(array_unique(array_merge($this->selectedAssetIds, $visibleIds)));
    }

    public function clearSelectedAssets(): void
    {
        $this->selectedAssetIds = [];
    }

    public function openBulkActionModal(string $actionType): void
    {
        if (! in_array($actionType, ['assign', 'return', 'dispose'], true)) {
            return;
        }

        if ($this->selectedAssetsCount < 1) {
            $this->setFeedbackError('Select at least one asset first.');

            return;
        }

        $this->resetValidation();
        $this->feedbackError = null;
        $this->bulkActionType = $actionType;
        $this->resetBulkForm();
        $this->bulkForm['summary'] = match ($actionType) {
            'assign' => 'Bulk custody update',
            'return' => 'Bulk return to inventory',
            'dispose' => 'Bulk disposal',
            default => '',
        };
        $this->bulkForm['event_date'] = $actionType === 'assign'
            ? now()->format('Y-m-d\\TH:i')
            : now()->toDateString();
        if ($actionType === 'assign') {
            $this->syncBulkDepartmentFromAssignee();
        }
        $this->showBulkActionModal = true;
    }

    public function closeBulkActionModal(): void
    {
        $this->showBulkActionModal = false;
        $this->bulkActionType = '';
        $this->resetBulkForm();
        $this->resetValidation();
    }

    public function saveBulkAction(AssignAsset $assignAsset, ReturnAsset $returnAsset, DisposeAsset $disposeAsset): void
    {
        if ($this->selectedAssetsCount < 1 || $this->bulkActionType === '') {
            $this->setFeedbackError('Select at least one asset and choose a bulk action.');

            return;
        }

        if ($this->bulkActionType === 'assign' && trim((string) $this->bulkForm['target_user_id']) === '') {
            $this->addError('bulkForm.target_user_id', 'Assignee is required.');

            return;
        }

        if ($this->bulkActionType === 'dispose' && trim((string) $this->bulkForm['summary']) === '') {
            $this->addError('bulkForm.summary', 'Reason summary is required.');

            return;
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            $this->setFeedbackError('Session expired. Sign in again.');

            return;
        }

        $assetIds = array_values(array_unique(array_map('intval', $this->selectedAssetIds)));
        $assets = Asset::query()
            ->where('company_id', (int) $user->company_id)
            ->whereIn('id', $assetIds)
            ->get();

        if ($assets->isEmpty()) {
            $this->setFeedbackError('No valid assets selected for bulk action.');
            $this->selectedAssetIds = [];
            $this->closeBulkActionModal();

            return;
        }

        $processed = 0;
        $failed = 0;
        $failedIds = [];

        // Execute action per asset so existing policy checks and audit/event logging remain intact.
        foreach ($assets as $asset) {
            try {
                match ($this->bulkActionType) {
                    'assign' => $assignAsset($user, $asset, $this->bulkAssignPayload()),
                    'return' => $returnAsset($user, $asset, $this->bulkReturnPayload()),
                    'dispose' => $disposeAsset($user, $asset, $this->bulkDisposePayload()),
                    default => null,
                };
                $processed++;
                $this->refreshHistoryIfSelected((int) $asset->id);
            } catch (Throwable) {
                $failed++;
                $failedIds[] = (int) $asset->id;
            }
        }

        $actionLabel = match ($this->bulkActionType) {
            'assign' => 'custody update',
            'return' => 'return to inventory',
            'dispose' => 'disposal',
            default => 'update',
        };

        if ($processed > 0 && $failed === 0) {
            $this->setFeedback("Bulk {$actionLabel} completed for {$processed} asset(s).");
        } elseif ($processed > 0) {
            $this->setFeedbackError("Bulk {$actionLabel} partially completed. {$processed} succeeded, {$failed} failed.");
        } else {
            $this->setFeedbackError("Bulk {$actionLabel} failed for all selected assets.");
        }

        $this->selectedAssetIds = $failed > 0 ? array_values(array_unique($failedIds)) : [];
        $this->closeBulkActionModal();
    }

    public function openHistoryModal(int $assetId): void
    {
        $asset = $this->findAssetOrFail($assetId);
        Gate::authorize('view', $asset);

        $this->selectedAssetId = $asset->id;
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->selectedAssetId = null;
    }

    public function getCanCreateAssetProperty(): bool
    {
        return Gate::allows('create', Asset::class);
    }

    public function getSelectedAssetsCountProperty(): int
    {
        return count($this->selectedAssetIds);
    }

    /**
     * @return array<int>
     */
    public function getVisibleAssetIdsProperty(): array
    {
        if (! $this->readyToLoad) {
            return [];
        }

        return $this->assetQuery()
            ->forPage((int) $this->getPage(), $this->perPage)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function getAllVisibleSelectedProperty(): bool
    {
        $visibleIds = $this->visibleAssetIds;

        return $visibleIds !== []
            && count(array_diff($visibleIds, $this->selectedAssetIds)) === 0;
    }

    public function updatedAssignmentFormTargetUserId(): void
    {
        $this->syncAssignmentDepartmentFromAssignee();
    }

    public function updatedBulkFormTargetUserId(): void
    {
        $this->syncBulkDepartmentFromAssignee();
    }

    public function getAssignmentDepartmentNameProperty(): string
    {
        $departmentId = (int) ($this->assignmentForm['target_department_id'] ?? 0);

        return $this->resolveDepartmentName($departmentId);
    }

    public function getBulkDepartmentNameProperty(): string
    {
        $departmentId = (int) ($this->bulkForm['target_department_id'] ?? 0);

        return $this->resolveDepartmentName($departmentId);
    }

    public function getSelectedAssetProperty(): ?Asset
    {
        if (! $this->selectedAssetId) {
            return null;
        }

        return Asset::query()
            ->with(['category:id,name', 'assignee:id,name', 'assignedDepartment:id,name'])
            ->find($this->selectedAssetId);
    }

    /**
     * @return array<int, AssetEvent>
     */
    public function getSelectedAssetHistoryProperty(): array
    {
        if (! $this->selectedAssetId) {
            return [];
        }

        return AssetEvent::query()
            ->where('asset_id', (int) $this->selectedAssetId)
            ->with(['actor:id,name', 'targetUser:id,name', 'targetDepartment:id,name'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function render(): View
    {
        $assets = $this->readyToLoad
            ? $this->assetQuery()->paginate($this->perPage)
            : Asset::query()->whereRaw('1 = 0')->paginate($this->perPage);

        return view('livewire.assets.assets-page', [
            'assets' => $assets,
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'statusOptions' => Asset::STATUSES,
            'conditionOptions' => ['excellent', 'good', 'fair', 'poor', 'damaged'],
        ]);
    }

    private function assetQuery()
    {
        return Asset::query()
            ->with(['category:id,name', 'assignee:id,name', 'assignedDepartment:id,name'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('asset_code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%')
                        ->orWhere('serial_number', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== 'all', fn ($query) => $query->where('asset_category_id', (int) $this->categoryFilter))
            ->when($this->assignmentFilter === 'assigned', fn ($query) => $query->whereNotNull('assigned_to_user_id')->where('status', '!=', Asset::STATUS_DISPOSED))
            ->when($this->assignmentFilter === 'unassigned', fn ($query) => $query->whereNull('assigned_to_user_id')->where('status', '!=', Asset::STATUS_DISPOSED))
            ->when($this->assignmentFilter === 'disposed', fn ($query) => $query->where('status', Asset::STATUS_DISPOSED))
            ->latest('created_at');
    }

    private function findAssetOrFail(int $assetId): Asset
    {
        /** @var Asset $asset */
        $asset = Asset::query()->findOrFail($assetId);

        return $asset;
    }

    /**
     * @return array<string, mixed>
     */
    private function assetPayload(): array
    {
        return [
            'asset_category_id' => $this->assetForm['asset_category_id'] !== '' ? (int) $this->assetForm['asset_category_id'] : null,
            'name' => $this->assetForm['name'],
            'serial_number' => $this->assetForm['serial_number'],
            'acquisition_date' => $this->assetForm['acquisition_date'] !== '' ? $this->assetForm['acquisition_date'] : null,
            'purchase_amount' => $this->assetForm['purchase_amount'] !== '' ? (int) $this->assetForm['purchase_amount'] : null,
            'currency' => $this->assetForm['currency'],
            'condition' => $this->assetForm['condition'],
            'notes' => $this->assetForm['notes'],
            'maintenance_due_date' => $this->assetForm['maintenance_due_date'] !== '' ? $this->assetForm['maintenance_due_date'] : null,
            'warranty_expires_at' => $this->assetForm['warranty_expires_at'] !== '' ? $this->assetForm['warranty_expires_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(): array
    {
        return [
            'name' => $this->categoryForm['name'],
            'description' => $this->categoryForm['description'],
            'is_active' => (bool) $this->categoryForm['is_active'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(): array
    {
        return [
            'target_user_id' => (int) $this->assignmentForm['target_user_id'],
            'target_department_id' => $this->assignmentForm['target_department_id'] !== '' ? (int) $this->assignmentForm['target_department_id'] : null,
            'event_date' => $this->assignmentForm['event_date'],
            'summary' => $this->assignmentForm['summary'],
            'details' => $this->assignmentForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(): array
    {
        return [
            'event_date' => $this->maintenanceForm['event_date'],
            'summary' => $this->maintenanceForm['summary'],
            'amount' => $this->maintenanceForm['amount'] !== '' ? (int) $this->maintenanceForm['amount'] : null,
            'currency' => $this->maintenanceForm['currency'],
            'details' => $this->maintenanceForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disposalPayload(): array
    {
        return [
            'event_date' => $this->disposalForm['event_date'],
            'summary' => $this->disposalForm['summary'],
            'salvage_amount' => $this->disposalForm['salvage_amount'] !== '' ? (int) $this->disposalForm['salvage_amount'] : null,
            'details' => $this->disposalForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function returnPayload(): array
    {
        return [
            'event_date' => $this->returnForm['event_date'],
            'summary' => $this->returnForm['summary'],
            'details' => $this->returnForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bulkAssignPayload(): array
    {
        return [
            'target_user_id' => (int) $this->bulkForm['target_user_id'],
            'target_department_id' => $this->bulkForm['target_department_id'] !== '' ? (int) $this->bulkForm['target_department_id'] : null,
            'event_date' => $this->bulkForm['event_date'],
            'summary' => $this->bulkForm['summary'],
            'details' => $this->bulkForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bulkReturnPayload(): array
    {
        return [
            'event_date' => $this->bulkForm['event_date'],
            'summary' => $this->bulkForm['summary'],
            'details' => $this->bulkForm['details'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bulkDisposePayload(): array
    {
        return [
            'event_date' => $this->bulkForm['event_date'],
            'summary' => $this->bulkForm['summary'],
            'salvage_amount' => $this->bulkForm['salvage_amount'] !== '' ? (int) $this->bulkForm['salvage_amount'] : null,
            'details' => $this->bulkForm['details'],
        ];
    }

    private function resetAssetForm(): void
    {
        $currency = strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN'));

        $this->assetForm = [
            'asset_category_id' => '',
            'name' => '',
            'serial_number' => '',
            'acquisition_date' => '',
            'purchase_amount' => '',
            'currency' => $currency,
            'condition' => 'good',
            'notes' => '',
            'maintenance_due_date' => '',
            'warranty_expires_at' => '',
        ];
    }

    private function resetCategoryForm(): void
    {
        $this->categoryForm = [
            'name' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    private function resetAssignmentForm(): void
    {
        $this->assignmentForm = [
            'target_user_id' => '',
            'target_department_id' => '',
            'event_date' => now()->format('Y-m-d\\TH:i'),
            'summary' => '',
            'details' => '',
        ];
    }

    private function resetMaintenanceForm(): void
    {
        $currency = strtoupper((string) (\Illuminate\Support\Facades\Auth::user()?->company?->currency_code ?: 'NGN'));
        $this->maintenanceForm = [
            'event_date' => now()->toDateString(),
            'summary' => '',
            'amount' => '',
            'currency' => $currency,
            'details' => '',
        ];
    }

    private function resetDisposalForm(): void
    {
        $this->disposalForm = [
            'event_date' => now()->toDateString(),
            'summary' => '',
            'salvage_amount' => '',
            'details' => '',
        ];
    }

    private function resetReturnForm(): void
    {
        $this->returnForm = [
            'event_date' => now()->toDateString(),
            'summary' => '',
            'details' => '',
        ];
    }

    private function resetBulkForm(): void
    {
        $this->bulkForm = [
            'target_user_id' => '',
            'target_department_id' => '',
            'event_date' => now()->toDateString(),
            'summary' => '',
            'details' => '',
            'salvage_amount' => '',
        ];
    }

    private function fillAssetForm(Asset $asset): void
    {
        $this->assetForm = [
            'asset_category_id' => (string) ($asset->asset_category_id ?? ''),
            'name' => (string) $asset->name,
            'serial_number' => (string) ($asset->serial_number ?? ''),
            'acquisition_date' => optional($asset->acquisition_date)->toDateString() ?? '',
            'purchase_amount' => $asset->purchase_amount !== null ? (string) $asset->purchase_amount : '',
            'currency' => (string) ($asset->currency ?: 'NGN'),
            'condition' => (string) ($asset->condition ?: 'good'),
            'notes' => (string) ($asset->notes ?? ''),
            'maintenance_due_date' => optional($asset->maintenance_due_date)->toDateString() ?? '',
            'warranty_expires_at' => optional($asset->warranty_expires_at)->toDateString() ?? '',
        ];
    }

    private function refreshHistoryIfSelected(int $assetId): void
    {
        if ($this->selectedAssetId === $assetId) {
            $this->selectedAssetId = $assetId;
        }
    }

    private function syncAssignmentDepartmentFromAssignee(): void
    {
        $departmentId = $this->resolveDepartmentIdForAssignee($this->assignmentForm['target_user_id'] ?? '');
        $this->assignmentForm['target_department_id'] = $departmentId > 0 ? (string) $departmentId : '';
    }

    private function syncBulkDepartmentFromAssignee(): void
    {
        $departmentId = $this->resolveDepartmentIdForAssignee($this->bulkForm['target_user_id'] ?? '');
        $this->bulkForm['target_department_id'] = $departmentId > 0 ? (string) $departmentId : '';
    }

    private function resolveDepartmentIdForAssignee(mixed $assigneeId): int
    {
        $userId = (int) $assigneeId;
        if ($userId <= 0) {
            return 0;
        }

        $departmentId = (int) (User::query()
            ->where('id', $userId)
            ->where('is_active', true)
            ->value('department_id') ?? 0);

        return $departmentId > 0 ? $departmentId : 0;
    }

    private function resolveDepartmentName(int $departmentId): string
    {
        if ($departmentId <= 0) {
            return 'No department assigned';
        }

        return (string) (Department::query()->where('id', $departmentId)->value('name') ?? 'No department assigned');
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
