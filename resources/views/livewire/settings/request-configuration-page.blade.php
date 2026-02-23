<div class="space-y-6">
    <div
        class="pointer-events-none fixed z-[95] space-y-2"
        style="right: 16px; top: 64px; width: 320px; max-width: calc(100vw - 24px);"
    >
        @if ($feedbackMessage)
            <div
                wire:key="request-config-feedback-success-{{ $feedbackKey }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3200)"
                x-show="show"
                x-transition.opacity.duration.250ms
                class="pointer-events-auto rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-lg"
            >
                {{ $feedbackMessage }}
            </div>
        @endif
    </div>

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Request Types
            </span>
            <h2 class="mt-2 text-base font-semibold text-slate-900">Company Request Type Rules</h2>
            <p class="mt-1 text-sm text-slate-600">Define which request types exist and what fields each type requires.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="openCreateRequestTypeModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateRequestTypeModal"
                        class="inline-flex min-w-[180px] items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span>Create Request Type</span>
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-800">Current Request Types</p>
                <div class="mt-3 space-y-2">
                    @forelse ($requestTypes as $type)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $type->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $type->code }} - {{ $type->is_active ? 'Active' : 'Inactive' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="editRequestType({{ $type->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="toggleRequestTypeActive({{ $type->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        {{ $type->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @if ($type->requires_amount)<span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Amount</span>@endif
                                @if ($type->requires_line_items)<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Line Items</span>@endif
                                @if ($type->requires_date_range)<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Date Range</span>@endif
                                @if ($type->requires_vendor)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Vendor</span>@endif
                                @if ($type->requires_attachments)<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">Attachments</span>@endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                            No request types configured.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="fd-card p-6">
        <div class="mb-4">
            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                Spend Categories
            </span>
            <h2 class="mt-2 text-base font-semibold text-slate-900">Controlled Spend Categories</h2>
            <p class="mt-1 text-sm text-slate-600">Use controlled categories to improve reporting quality and consistency.</p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="openCreateSpendCategoryModal"
                        wire:loading.attr="disabled"
                        wire:target="openCreateSpendCategoryModal"
                        class="inline-flex min-w-[210px] items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-70"
                    >
                        <span>Create Spend Category</span>
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-sm font-semibold text-slate-800">Current Spend Categories</p>
                <div class="mt-3 space-y-2">
                    @forelse ($spendCategories as $category)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $category->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $category->code }} - {{ $category->is_active ? 'Active' : 'Inactive' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="editSpendCategory({{ $category->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="toggleSpendCategoryActive({{ $category->id }})" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                        {{ $category->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                            No spend categories configured.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if ($showRequestTypeModal)
        <div wire:click="closeRequestTypeModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Request Types
                            </span>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">{{ $editingRequestTypeId ? 'Edit Request Type' : 'Create Request Type' }}</h2>
                        </div>
                        <button type="button" wire:click="closeRequestTypeModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="saveRequestType" class="space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Name</span>
                            <input type="text" wire:model.defer="requestTypeForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Spend">
                            @error('requestTypeForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code (Optional)</span>
                            <input type="text" wire:model.defer="requestTypeForm.code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Auto-generated if blank">
                            @error('requestTypeForm.code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Description</span>
                            <textarea wire:model.defer="requestTypeForm.description" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('requestTypeForm.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.is_active" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Active
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.requires_amount" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Requires Amount
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.requires_line_items" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Requires Line Items
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.requires_date_range" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Requires Date Range
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.requires_vendor" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Requires Vendor
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" wire:model.defer="requestTypeForm.requires_attachments" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                                Requires Attachments
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeRequestTypeModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                {{ $editingRequestTypeId ? 'Update Type' : 'Create Type' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showSpendCategoryModal)
        <div wire:click="closeSpendCategoryModal" class="fixed left-0 right-0 bottom-0 top-0 z-50 overflow-y-auto bg-slate-900/40 p-3">
            <div class="flex items-start justify-center pt-1">
                <div wire:click.stop class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" style="max-height: calc(100vh - 3rem); overflow-y: auto;">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-800">
                                Spend Categories
                            </span>
                            <h2 class="mt-2 text-base font-semibold text-slate-900">{{ $editingSpendCategoryId ? 'Edit Spend Category' : 'Create Spend Category' }}</h2>
                        </div>
                        <button type="button" wire:click="closeSpendCategoryModal" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <form wire:submit.prevent="saveSpendCategory" class="space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Name</span>
                            <input type="text" wire:model.defer="spendCategoryForm.name" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Operations">
                            @error('spendCategoryForm.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Code (Optional)</span>
                            <input type="text" wire:model.defer="spendCategoryForm.code" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Auto-generated if blank">
                            @error('spendCategoryForm.code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Description</span>
                            <textarea wire:model.defer="spendCategoryForm.description" rows="2" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('spendCategoryForm.description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </label>

                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" wire:model.defer="spendCategoryForm.is_active" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
                            Active
                        </label>

                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeSpendCategoryModal" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                                {{ $editingSpendCategoryId ? 'Update Category' : 'Create Category' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
