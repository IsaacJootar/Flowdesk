<div wire:init="loadData" class="space-y-5">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 72px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="asset-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif
        @if ($feedbackError)
            <div
                wire:key="asset-feedback-error-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-lg"
            >
                {{ $feedbackError }}
            </div>
        @endif
    </div>

    <div class="fd-card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="block lg:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Search</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                    placeholder="Asset code, name, serial"
                >
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</span>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All statuses</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucwords(str_replace('_', ' ', $statusOption)) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Category</span>
                <select wire:model.live="categoryFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Custody</span>
                <select wire:model.live="assignmentFilter" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="all">All</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                    <option value="disposed">Disposed</option>
                </select>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Asset register, custody tracking, maintenance history, and disposal trail.</p>
            <div class="inline-flex items-center gap-2">
                <a
                    href="{{ route('assets.reports') }}"
                    class="inline-flex items-center rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                >
                    Asset Reports
                </a>
                <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span>Rows</span>
                    <select wire:model.live="perPage" class="rounded-lg border-slate-300 text-xs focus:border-slate-500 focus:ring-slate-500">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </label>
                @if ($this->canCreateAsset)
                    <button
                        type="button"
                        wire:click="openCategoryModal"
                        wire:loading.attr="disabled"
                        wire:target="openCategoryModal"
                        class="inline-flex items-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCategoryModal">Add Category</span>
                        <span wire:loading wire:target="openCategoryModal">Opening...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="openCreateAssetModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateAssetModal"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="openCreateAssetModal">Register Asset</span>
                        <span wire:loading wire:target="openCreateAssetModal">Opening...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="fd-card overflow-hidden">
        @if (! $readyToLoad)
            <div class="space-y-3 p-4">
                @for ($i = 0; $i < 8; $i++)
                    <div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                @endfor
            </div>
        @else
            <div wire:loading.flex wire:target="search,statusFilter,categoryFilter,assignmentFilter,perPage,gotoPage,previousPage,nextPage,toggleAssetSelection,toggleSelectVisibleAssets,saveBulkAction" class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500">
                Loading assets...
            </div>

            @if ($this->selectedAssetsCount > 0)
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-medium text-slate-700">
                            {{ number_format($this->selectedAssetsCount) }} asset(s) selected
                        </p>
                        <div class="inline-flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                wire:click="openBulkActionModal('assign')"
                                class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                            >
                                Bulk Assign
                            </button>
                            <button
                                type="button"
                                wire:click="openBulkActionModal('return')"
                                class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100"
                            >
                                Bulk Return
                            </button>
                            <button
                                type="button"
                                wire:click="openBulkActionModal('dispose')"
                                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                            >
                                Bulk Dispose
                            </button>
                            <button
                                type="button"
                                wire:click="clearSelectedAssets"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-white"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left font-semibold">
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelectVisibleAssets"
                                    @checked($this->allVisibleSelected)
                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                >
                            </th>
                            <th class="px-4 py-3 text-left font-semibold">Asset</th>
                            <th class="px-4 py-3 text-left font-semibold">Category</th>
                            <th class="px-4 py-3 text-left font-semibold">Custody</th>
                            <th class="px-4 py-3 text-left font-semibold">Condition</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assets as $asset)
                            @php
                                $statusClass = match ($asset->status) {
                                    'assigned' => 'bg-blue-100 text-blue-700',
                                    'in_maintenance' => 'bg-amber-100 text-amber-700',
                                    'disposed' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };
                            @endphp
                            <tr wire:key="asset-row-{{ $asset->id }}" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleAssetSelection({{ $asset->id }})"
                                        @checked(in_array((int) $asset->id, $selectedAssetIds, true))
                                        class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $asset->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $asset->asset_code }} @if($asset->serial_number)&middot; {{ $asset->serial_number }}@endif</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $asset->category?->name ?? 'Uncategorized' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($asset->assigned_to_user_id)
                                        <p class="font-medium text-slate-800">{{ $asset->assignee?->name ?? 'Assigned user removed' }}</p>
                                        <p class="text-xs text-slate-500">{{ $asset->assignedDepartment?->name ?? 'No department' }}</p>
                                    @else
                                        <span class="text-slate-500">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ ucwords((string) $asset->condition) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ ucwords(str_replace('_', ' ', (string) $asset->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="openHistoryModal({{ $asset->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            History
                                        </button>
                                        @can('update', $asset)
                                            <button
                                                type="button"
                                                wire:click="openEditAssetModal({{ $asset->id }})"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                            >
                                                Edit
                                            </button>
                                        @endcan
                                        @can('assign', $asset)
                                            @if ($asset->status !== 'disposed')
                                                <button
                                                    type="button"
                                                    wire:click="openAssignmentModal({{ $asset->id }})"
                                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                                >
                                                    {{ $asset->assigned_to_user_id ? 'Transfer' : 'Assign' }}
                                                </button>
                                                @if ($asset->assigned_to_user_id)
                                                    <button
                                                        type="button"
                                                        wire:click="openReturnModal({{ $asset->id }})"
                                                        class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100"
                                                    >
                                                        Return
                                                    </button>
                                                @endif
                                            @endif
                                        @endcan
                                        @can('logMaintenance', $asset)
                                            @if ($asset->status !== 'disposed')
                                                <button
                                                    type="button"
                                                    wire:click="openMaintenanceModal({{ $asset->id }})"
                                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100"
                                                >
                                                    Maintenance
                                                </button>
                                            @endif
                                        @endcan
                                        @can('dispose', $asset)
                                            @if ($asset->status !== 'disposed')
                                                <button
                                                    type="button"
                                                    wire:click="openDisposalModal({{ $asset->id }})"
                                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                                >
                                                    Dispose
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No assets found. @if ($this->canCreateAsset)Register your first asset to start custody tracking.@endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $assets->links() }}
            </div>
        @endif
    </div>

    @if ($showAssetModal)
        <div wire:click="closeAssetModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-700">
                                Asset Profile
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $isEditingAsset ? 'Edit Asset' : 'Register Asset' }}</h2>
                        </div>
                        <button type="button" wire:click="closeAssetModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveAsset" class="space-y-4">
                        @error('assetForm.no_changes')
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ $message }}</div>
                        @enderror

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Asset Name</span>
                                <input type="text" wire:model.defer="assetForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Category</span>
                                <select wire:model.defer="assetForm.asset_category_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Serial Number</span>
                                <input type="text" wire:model.defer="assetForm.serial_number" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Acquisition Date</span>
                                <input type="date" wire:model.defer="assetForm.acquisition_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Maintenance Due Date</span>
                                <input type="date" wire:model.defer="assetForm.maintenance_due_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Warranty Expiry Date</span>
                                <input type="date" wire:model.defer="assetForm.warranty_expires_at" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Purchase Amount</span>
                                <input type="number" min="0" wire:model.defer="assetForm.purchase_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Currency</span>
                                <input type="text" wire:model.defer="assetForm.currency" class="w-full rounded-xl border-slate-300 text-sm uppercase focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Condition</span>
                                <select wire:model.defer="assetForm.condition" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    @foreach ($conditionOptions as $conditionOption)
                                        <option value="{{ $conditionOption }}">{{ ucfirst($conditionOption) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                            <textarea wire:model.defer="assetForm.notes" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>

                        <div class="sticky bottom-0 -mx-6 mt-4 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
                            <button type="button" wire:click="closeAssetModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveAsset" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveAsset">{{ $isEditingAsset ? 'Update Asset' : 'Save Asset' }}</span>
                                <span wire:loading wire:target="saveAsset">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showCategoryModal)
        <div wire:click="closeCategoryModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-700">
                                Asset Category
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Create Category</h2>
                        </div>
                        <button type="button" wire:click="closeCategoryModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveCategory" class="space-y-4">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Name</span>
                            <input type="text" wire:model.live.debounce.300ms="categoryForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="inline-flex items-center gap-2 pt-6 text-sm text-slate-700">
                                <input type="checkbox" wire:model.defer="categoryForm.is_active" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                Active category
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Description</span>
                            <textarea wire:model.defer="categoryForm.description" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeCategoryModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveCategory" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveCategory">Save Category</span>
                                <span wire:loading wire:target="saveCategory">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showAssignmentModal)
        <div wire:click="closeAssignmentModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">
                                Custody Update
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Assign / Transfer Asset</h2>
                        </div>
                        <button type="button" wire:click="closeAssignmentModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveAssignment" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Assignee</span>
                                <select wire:model.live="assignmentForm.target_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                    <option value="">Select staff</option>
                                    @foreach ($assignees as $assignee)
                                        <option value="{{ $assignee->id }}">{{ $assignee->name }} ({{ ucfirst($assignee->role) }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Department</span>
                                <input type="text" value="{{ $this->assignmentDepartmentName }}" readonly class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm text-slate-600 focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Effective Date &amp; Time</span>
                                <input type="datetime-local" wire:model.defer="assignmentForm.event_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Summary</span>
                                <input type="text" wire:model.defer="assignmentForm.summary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                            <textarea wire:model.defer="assignmentForm.details" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeAssignmentModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveAssignment" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveAssignment">Save Custody</span>
                                <span wire:loading wire:target="saveAssignment">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showReturnModal)
        <div wire:click="closeReturnModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-sky-700">
                                Return to Inventory
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Return Asset</h2>
                        </div>
                        <button type="button" wire:click="closeReturnModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveReturn" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Return Date</span>
                                <input type="date" wire:model.defer="returnForm.event_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Summary</span>
                                <input type="text" wire:model.defer="returnForm.summary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                            <textarea wire:model.defer="returnForm.details" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeReturnModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveReturn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveReturn">Confirm Return</span>
                                <span wire:loading wire:target="saveReturn">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showMaintenanceModal)
        <div wire:click="closeMaintenanceModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-amber-700">
                                Maintenance Log
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Record Maintenance</h2>
                        </div>
                        <button type="button" wire:click="closeMaintenanceModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveMaintenance" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Date</span>
                                <input type="date" wire:model.defer="maintenanceForm.event_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Summary</span>
                                <input type="text" wire:model.defer="maintenanceForm.summary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Amount</span>
                                <input type="number" min="0" wire:model.defer="maintenanceForm.amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Currency</span>
                                <input type="text" wire:model.defer="maintenanceForm.currency" class="w-full rounded-xl border-slate-300 text-sm uppercase focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Details</span>
                            <textarea wire:model.defer="maintenanceForm.details" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>
                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeMaintenanceModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveMaintenance" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveMaintenance">Save Log</span>
                                <span wire:loading wire:target="saveMaintenance">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showDisposalModal)
        <div wire:click="closeDisposalModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-rose-700">
                                Disposal
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">Dispose Asset</h2>
                        </div>
                        <button type="button" wire:click="closeDisposalModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveDisposal" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Disposal Date</span>
                                <input type="date" wire:model.defer="disposalForm.event_date" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Salvage Amount</span>
                                <input type="number" min="0" wire:model.defer="disposalForm.salvage_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Reason Summary</span>
                            <input type="text" wire:model.defer="disposalForm.summary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Details</span>
                            <textarea wire:model.defer="disposalForm.details" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>
                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeDisposalModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveDisposal" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveDisposal">Confirm Disposal</span>
                                <span wire:loading wire:target="saveDisposal">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showBulkActionModal)
        @php
            $bulkBadgeClass = match ($bulkActionType) {
                'assign' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                'return' => 'border-sky-200 bg-sky-50 text-sky-700',
                'dispose' => 'border-rose-200 bg-rose-50 text-rose-700',
                default => 'border-slate-200 bg-slate-50 text-slate-700',
            };
            $bulkTitle = match ($bulkActionType) {
                'assign' => 'Bulk Assign / Transfer',
                'return' => 'Bulk Return to Inventory',
                'dispose' => 'Bulk Dispose',
                default => 'Bulk Action',
            };
        @endphp
        <div wire:click="closeBulkActionModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-2xl p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $bulkBadgeClass }}">
                                Bulk Operation
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $bulkTitle }}</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ number_format($this->selectedAssetsCount) }} asset(s) selected</p>
                        </div>
                        <button type="button" wire:click="closeBulkActionModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <form wire:submit.prevent="saveBulkAction" class="space-y-4">
                        @if ($bulkActionType === 'assign')
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Assignee</span>
                                    <select wire:model.live="bulkForm.target_user_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="">Select staff</option>
                                        @foreach ($assignees as $assignee)
                                            <option value="{{ $assignee->id }}">{{ $assignee->name }} ({{ ucfirst($assignee->role) }})</option>
                                        @endforeach
                                    </select>
                                    @error('bulkForm.target_user_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Department</span>
                                    <input type="text" value="{{ $this->bulkDepartmentName }}" readonly class="w-full rounded-xl border-slate-300 bg-slate-50 text-sm text-slate-600 focus:border-slate-500 focus:ring-slate-500">
                                </label>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-slate-700">
                                    {{
                                        $bulkActionType === 'dispose'
                                            ? 'Disposal Date'
                                            : ($bulkActionType === 'assign' ? 'Effective Date & Time' : 'Effective Date')
                                    }}
                                </span>
                                <input
                                    type="{{ $bulkActionType === 'assign' ? 'datetime-local' : 'date' }}"
                                    wire:model.defer="bulkForm.event_date"
                                    class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                                >
                            </label>
                            @if ($bulkActionType === 'dispose')
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-700">Salvage Amount</span>
                                    <input type="number" min="0" wire:model.defer="bulkForm.salvage_amount" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                                </label>
                            @endif
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">{{ $bulkActionType === 'dispose' ? 'Reason Summary' : 'Summary' }}</span>
                            <input type="text" wire:model.defer="bulkForm.summary" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('bulkForm.summary')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                            <textarea wire:model.defer="bulkForm.details" rows="3" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        </label>

                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeBulkActionModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveBulkAction" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveBulkAction">Apply Bulk Action</span>
                                <span wire:loading wire:target="saveBulkAction">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showHistoryModal)
        <div wire:click="closeHistoryModal" class="fixed inset-0 z-40 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="fd-card w-full max-w-3xl p-6" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-700">
                                Custody History
                            </span>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $this->selectedAsset?->name ?? 'Asset' }}</h2>
                            <p class="text-xs text-slate-500">{{ $this->selectedAsset?->asset_code ?? '' }}</p>
                        </div>
                        <button type="button" wire:click="closeHistoryModal" class="rounded-lg border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50">Close</button>
                    </div>

                    <div class="space-y-2">
                        @forelse ($this->selectedAssetHistory as $event)
                            @php
                                $eventClass = match ($event->event_type) {
                                    'disposed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'maintenance' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'returned' => 'border-sky-200 bg-sky-50 text-sky-700',
                                    'assigned', 'transferred' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                    default => 'border-slate-200 bg-white text-slate-700',
                                };
                            @endphp
                            <div class="rounded-lg border px-3 py-3 {{ $eventClass }}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold">{{ ucwords(str_replace('_', ' ', (string) $event->event_type)) }}</p>
                                        <p class="text-xs">{{ $event->summary ?: 'No summary' }}</p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <p>{{ optional($event->event_date)->format('M d, Y H:i') }}</p>
                                        <p>{{ $event->actor?->name ?: 'System' }}</p>
                                    </div>
                                </div>
                                @if ($event->targetUser || $event->targetDepartment)
                                    <p class="mt-1 text-xs">
                                        Target:
                                        {{ $event->targetUser?->name ?: 'No user' }}
                                        @if ($event->targetDepartment)
                                            &middot; {{ $event->targetDepartment->name }}
                                        @endif
                                    </p>
                                @endif
                                @if ($event->amount !== null)
                                    <p class="mt-1 text-xs">
                                        Amount: {{ strtoupper((string) ($event->currency ?: 'NGN')) }} {{ number_format((int) $event->amount) }}
                                    </p>
                                @endif
                                @if ($event->details)
                                    <p class="mt-2 text-xs">{{ $event->details }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">
                                No history entries for this asset yet.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
