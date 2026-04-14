<?php

namespace App\Livewire\Settings;

use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;
use App\Domains\Requests\Models\CompanySpendCategory;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Settings')]
class RequestConfigurationPage extends Component
{
    public bool $showRequestTypeModal = false;

    public bool $showSpendCategoryModal = false;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public ?int $editingRequestTypeId = null;

    public ?int $editingSpendCategoryId = null;

    /** @var array<string, mixed> */
    public array $requestTypeForm = [
        'name' => '',
        'code' => '',
        'description' => '',
        'is_active' => true,
        'requires_amount' => true,
        'requires_line_items' => false,
        'requires_date_range' => false,
        'requires_vendor' => false,
        'requires_attachments' => false,
    ];

    /** @var array<string, mixed> */
    public array $spendCategoryForm = [
        'name' => '',
        'code' => '',
        'description' => '',
        'is_active' => true,
    ];

    public string $budget_guardrail_mode = CompanyRequestPolicySetting::BUDGET_MODE_WARN;

    public bool $duplicate_detection_enabled = true;

    public int $duplicate_window_days = 30;

    public function mount(): void
    {
        $this->authorizeOwner();
        $this->hydratePolicySettings();
    }

    public function openCreateRequestTypeModal(): void
    {
        $this->authorizeOwner();
        $this->resetRequestTypeForm();
        $this->resetValidation();
        $this->showRequestTypeModal = true;
    }

    public function closeRequestTypeModal(): void
    {
        $this->showRequestTypeModal = false;
        $this->resetRequestTypeForm();
        $this->resetValidation();
    }

    public function openCreateSpendCategoryModal(): void
    {
        $this->authorizeOwner();
        $this->resetSpendCategoryForm();
        $this->resetValidation();
        $this->showSpendCategoryModal = true;
    }

    public function closeSpendCategoryModal(): void
    {
        $this->showSpendCategoryModal = false;
        $this->resetSpendCategoryForm();
        $this->resetValidation();
    }

    /**
     * @throws ValidationException
     */
    public function saveRequestType(): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $companyId = (int) \Illuminate\Support\Facades\Auth::user()->company_id;
        $typeId = $this->editingRequestTypeId;
        $validated = $this->validate([
            'requestTypeForm.name' => ['required', 'string', 'max:80'],
            'requestTypeForm.code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('company_request_types', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($typeId),
            ],
            'requestTypeForm.description' => ['nullable', 'string', 'max:400'],
            'requestTypeForm.is_active' => ['boolean'],
            'requestTypeForm.requires_amount' => ['boolean'],
            'requestTypeForm.requires_line_items' => ['boolean'],
            'requestTypeForm.requires_date_range' => ['boolean'],
            'requestTypeForm.requires_vendor' => ['boolean'],
            'requestTypeForm.requires_attachments' => ['boolean'],
        ]);

        $codeInput = (string) ($validated['requestTypeForm']['code'] ?? '');
        $code = Str::slug($codeInput !== '' ? $codeInput : (string) $validated['requestTypeForm']['name'], '_');
        if ($code === '') {
            $code = 'request_type';
        }

        if ((bool) $validated['requestTypeForm']['requires_line_items']) {
            $validated['requestTypeForm']['requires_amount'] = true;
        }

        if ($this->editingRequestTypeId) {
            $type = CompanyRequestType::query()->findOrFail($this->editingRequestTypeId);
            $type->forceFill([
                'name' => (string) $validated['requestTypeForm']['name'],
                'code' => $code,
                'description' => $validated['requestTypeForm']['description'] ?: null,
                'is_active' => (bool) $validated['requestTypeForm']['is_active'],
                'requires_amount' => (bool) $validated['requestTypeForm']['requires_amount'],
                'requires_line_items' => (bool) $validated['requestTypeForm']['requires_line_items'],
                'requires_date_range' => (bool) $validated['requestTypeForm']['requires_date_range'],
                'requires_vendor' => (bool) $validated['requestTypeForm']['requires_vendor'],
                'requires_attachments' => (bool) $validated['requestTypeForm']['requires_attachments'],
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
            ])->save();

            $this->setFeedback('Request type updated.');
        } else {
            CompanyRequestType::query()->create([
                'company_id' => $companyId,
                'name' => (string) $validated['requestTypeForm']['name'],
                'code' => $code,
                'description' => $validated['requestTypeForm']['description'] ?: null,
                'is_active' => (bool) $validated['requestTypeForm']['is_active'],
                'requires_amount' => (bool) $validated['requestTypeForm']['requires_amount'],
                'requires_line_items' => (bool) $validated['requestTypeForm']['requires_line_items'],
                'requires_date_range' => (bool) $validated['requestTypeForm']['requires_date_range'],
                'requires_vendor' => (bool) $validated['requestTypeForm']['requires_vendor'],
                'requires_attachments' => (bool) $validated['requestTypeForm']['requires_attachments'],
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            $this->setFeedback('Request type created.');
        }

        $this->resetRequestTypeForm();
        $this->showRequestTypeModal = false;
    }

    public function editRequestType(int $typeId): void
    {
        $this->authorizeOwner();
        $type = CompanyRequestType::query()->findOrFail($typeId);

        $this->editingRequestTypeId = (int) $type->id;
        $this->requestTypeForm = [
            'name' => (string) $type->name,
            'code' => (string) $type->code,
            'description' => (string) ($type->description ?? ''),
            'is_active' => (bool) $type->is_active,
            'requires_amount' => (bool) $type->requires_amount,
            'requires_line_items' => (bool) $type->requires_line_items,
            'requires_date_range' => (bool) $type->requires_date_range,
            'requires_vendor' => (bool) $type->requires_vendor,
            'requires_attachments' => (bool) $type->requires_attachments,
        ];
        $this->showRequestTypeModal = true;
    }

    public function cancelRequestTypeEdit(): void
    {
        $this->closeRequestTypeModal();
    }

    public function toggleRequestTypeActive(int $typeId): void
    {
        $this->authorizeOwner();
        $type = CompanyRequestType::query()->findOrFail($typeId);
        $type->forceFill([
            'is_active' => ! (bool) $type->is_active,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->setFeedback('Request type status updated.');
    }

    /**
     * @throws ValidationException
     */
    public function saveSpendCategory(): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $companyId = (int) \Illuminate\Support\Facades\Auth::user()->company_id;
        $categoryId = $this->editingSpendCategoryId;
        $validated = $this->validate([
            'spendCategoryForm.name' => ['required', 'string', 'max:80'],
            'spendCategoryForm.code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('company_spend_categories', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($categoryId),
            ],
            'spendCategoryForm.description' => ['nullable', 'string', 'max:300'],
            'spendCategoryForm.is_active' => ['boolean'],
        ]);

        $codeInput = (string) ($validated['spendCategoryForm']['code'] ?? '');
        $code = Str::slug($codeInput !== '' ? $codeInput : (string) $validated['spendCategoryForm']['name'], '_');
        if ($code === '') {
            $code = 'category';
        }

        if ($this->editingSpendCategoryId) {
            $category = CompanySpendCategory::query()->findOrFail($this->editingSpendCategoryId);
            $category->forceFill([
                'name' => (string) $validated['spendCategoryForm']['name'],
                'code' => $code,
                'description' => $validated['spendCategoryForm']['description'] ?: null,
                'is_active' => (bool) $validated['spendCategoryForm']['is_active'],
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
            ])->save();

            $this->setFeedback('Spend category updated.');
        } else {
            CompanySpendCategory::query()->create([
                'company_id' => $companyId,
                'name' => (string) $validated['spendCategoryForm']['name'],
                'code' => $code,
                'description' => $validated['spendCategoryForm']['description'] ?: null,
                'is_active' => (bool) $validated['spendCategoryForm']['is_active'],
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            $this->setFeedback('Spend category created.');
        }

        $this->resetSpendCategoryForm();
        $this->showSpendCategoryModal = false;
    }

    public function editSpendCategory(int $categoryId): void
    {
        $this->authorizeOwner();
        $category = CompanySpendCategory::query()->findOrFail($categoryId);

        $this->editingSpendCategoryId = (int) $category->id;
        $this->spendCategoryForm = [
            'name' => (string) $category->name,
            'code' => (string) $category->code,
            'description' => (string) ($category->description ?? ''),
            'is_active' => (bool) $category->is_active,
        ];
        $this->showSpendCategoryModal = true;
    }

    public function cancelSpendCategoryEdit(): void
    {
        $this->closeSpendCategoryModal();
    }

    public function toggleSpendCategoryActive(int $categoryId): void
    {
        $this->authorizeOwner();
        $category = CompanySpendCategory::query()->findOrFail($categoryId);
        $category->forceFill([
            'is_active' => ! (bool) $category->is_active,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->setFeedback('Spend category status updated.');
    }

    public function render(): View
    {
        $requestTypes = CompanyRequestType::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $spendCategories = CompanySpendCategory::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('livewire.settings.request-configuration-page', [
            'requestTypes' => $requestTypes,
            'spendCategories' => $spendCategories,
            'budgetModes' => CompanyRequestPolicySetting::BUDGET_MODES,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function savePolicySettings(): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $validated = $this->validate([
            'budget_guardrail_mode' => ['required', Rule::in(CompanyRequestPolicySetting::BUDGET_MODES)],
            'duplicate_detection_enabled' => ['boolean'],
            'duplicate_window_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $setting = $this->policySettingsRecord();
        $setting->forceFill([
            'budget_guardrail_mode' => (string) $validated['budget_guardrail_mode'],
            'duplicate_detection_enabled' => (bool) $validated['duplicate_detection_enabled'],
            'duplicate_window_days' => (int) $validated['duplicate_window_days'],
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->setFeedback('Request policy settings updated.');
    }

    private function resetRequestTypeForm(): void
    {
        $this->editingRequestTypeId = null;
        $this->requestTypeForm = [
            'name' => '',
            'code' => '',
            'description' => '',
            'is_active' => true,
            'requires_amount' => true,
            'requires_line_items' => false,
            'requires_date_range' => false,
            'requires_vendor' => false,
            'requires_attachments' => false,
        ];
    }

    private function resetSpendCategoryForm(): void
    {
        $this->editingSpendCategoryId = null;
        $this->spendCategoryForm = [
            'name' => '',
            'code' => '',
            'description' => '',
            'is_active' => true,
        ];
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function hydratePolicySettings(): void
    {
        $setting = $this->policySettingsRecord();
        $this->budget_guardrail_mode = (string) $setting->budget_guardrail_mode;
        $this->duplicate_detection_enabled = (bool) $setting->duplicate_detection_enabled;
        $this->duplicate_window_days = (int) $setting->duplicate_window_days;
    }

    private function policySettingsRecord(): CompanyRequestPolicySetting
    {
        return CompanyRequestPolicySetting::query()
            ->firstOrCreate(
                ['company_id' => (int) \Illuminate\Support\Facades\Auth::user()->company_id],
                array_merge(
                    CompanyRequestPolicySetting::defaultAttributes(),
                    ['created_by' => \Illuminate\Support\Facades\Auth::id()]
                )
            );
    }

    private function authorizeOwner(): void
    {
        if (! \Illuminate\Support\Facades\Auth::check() || \Illuminate\Support\Facades\Auth::user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage request configuration.');
        }
    }
}


