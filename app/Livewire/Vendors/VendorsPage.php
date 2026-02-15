<?php

namespace App\Livewire\Vendors;

use App\Actions\Vendors\CreateVendor;
use App\Actions\Vendors\DeleteVendor;
use App\Actions\Vendors\UpdateVendor;
use App\Domains\Vendors\Models\Vendor;
use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class VendorsPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public bool $showFormModal = false;

    public bool $showDetailPanel = false;

    public bool $isEditing = false;

    public ?int $editingVendorId = null;

    public ?int $selectedVendorId = null;

    public ?string $feedbackMessage = null;

    public int $feedbackKey = 0;

    public ?string $feedbackError = null;

    /** @var array<string, mixed> */
    public array $form = [
        'name' => '',
        'vendor_type' => '',
        'contact_person' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'bank_name' => '',
        'account_name' => '',
        'account_number' => '',
        'notes' => '',
        'is_active' => true,
    ];

    public function mount(): void
    {
        $this->feedbackMessage = session('status');
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

    /**
     * @throws AuthorizationException
     */
    public function openCreateModal(): void
    {
        Gate::authorize('create', Vendor::class);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingVendorId = null;
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function openEditModal(int $vendorId): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        Gate::authorize('update', $vendor);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->isEditing = true;
        $this->editingVendorId = $vendor->id;
        $this->fillFormFromVendor($vendor);
        $this->showFormModal = true;
    }

    /**
     * @throws AuthorizationException
     */
    public function showDetails(int $vendorId): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        Gate::authorize('view', $vendor);

        $this->selectedVendorId = $vendor->id;
        $this->showDetailPanel = true;
    }

    public function closeDetailPanel(): void
    {
        $this->showDetailPanel = false;
        $this->selectedVendorId = null;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
        $this->isEditing = false;
        $this->editingVendorId = null;
        $this->resetValidation();
    }

    /**
     * @throws AuthorizationException
     */
    public function save(CreateVendor $createVendor, UpdateVendor $updateVendor): void
    {
        $this->feedbackError = null;
        $this->validate();

        try {
            if ($this->isEditing && $this->editingVendorId) {
                $vendor = $this->findVendorOrFail($this->editingVendorId);
                $updateVendor(auth()->user(), $vendor, $this->formPayload());
                $this->setFeedback('Vendor updated successfully.');
            } else {
                $createVendor(auth()->user(), $this->formPayload());
                $this->setFeedback('Vendor created successfully.');
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable) {
            $this->feedbackError = 'Save failed. Please try again.';
            return;
        }

        $this->closeFormModal();
        $this->resetPage();
    }

    /**
     * @throws AuthorizationException
     */
    public function delete(int $vendorId, DeleteVendor $deleteVendor): void
    {
        $vendor = $this->findVendorOrFail($vendorId);
        $deleteVendor(auth()->user(), $vendor);

        if ($this->selectedVendorId === $vendorId) {
            $this->closeDetailPanel();
        }

        $this->setFeedback('Vendor deleted successfully.');
        $this->resetPage();
    }

    public function getCanManageProperty(): bool
    {
        return Gate::allows('create', Vendor::class);
    }

    public function getSelectedVendorProperty(): ?Vendor
    {
        if (! $this->selectedVendorId) {
            return null;
        }

        return Vendor::query()->find($this->selectedVendorId);
    }

    public function render(): View
    {
        $vendors = $this->readyToLoad
            ? $this->vendorQuery()->paginate(10)
            : Vendor::query()->whereRaw('1 = 0')->paginate(10);

        return view('livewire.vendors.vendors-page', [
            'vendors' => $vendors,
            'vendorTypes' => ['supplier', 'contractor', 'service', 'other'],
        ]);
    }

    private function vendorQuery()
    {
        return Vendor::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('contact_person', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->typeFilter !== 'all', fn ($query) => $query->where('vendor_type', $this->typeFilter))
            ->latest('created_at');
    }

    private function findVendorOrFail(int $vendorId): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = Vendor::query()->findOrFail($vendorId);

        return $vendor;
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        return [
            'name' => $this->nullableString($this->form['name']),
            'vendor_type' => $this->nullableString($this->form['vendor_type']),
            'contact_person' => $this->nullableString($this->form['contact_person']),
            'phone' => $this->nullableString($this->form['phone']),
            'email' => $this->nullableString($this->form['email']),
            'address' => $this->nullableString($this->form['address']),
            'bank_name' => $this->nullableString($this->form['bank_name']),
            'account_name' => $this->nullableString($this->form['account_name']),
            'account_number' => $this->nullableString($this->form['account_number']),
            'notes' => $this->nullableString($this->form['notes']),
            'is_active' => (bool) $this->form['is_active'],
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'vendor_type' => '',
            'contact_person' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fillFormFromVendor(Vendor $vendor): void
    {
        $this->form = [
            'name' => (string) $vendor->name,
            'vendor_type' => (string) ($vendor->vendor_type ?? ''),
            'contact_person' => (string) ($vendor->contact_person ?? ''),
            'phone' => (string) ($vendor->phone ?? ''),
            'email' => (string) ($vendor->email ?? ''),
            'address' => (string) ($vendor->address ?? ''),
            'bank_name' => (string) ($vendor->bank_name ?? ''),
            'account_name' => (string) ($vendor->account_name ?? ''),
            'account_number' => (string) ($vendor->account_number ?? ''),
            'notes' => (string) ($vendor->notes ?? ''),
            'is_active' => (bool) $vendor->is_active,
        ];
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = [
            'name',
            'vendor_type',
            'contact_person',
            'phone',
            'email',
            'address',
            'bank_name',
            'account_name',
            'account_number',
            'notes',
            'is_active',
        ];

        foreach ($errors as $key => $messages) {
            if (str_starts_with($key, 'form.')) {
                $mapped[$key] = $messages;
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

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:180'],
            'form.vendor_type' => ['required', Rule::in(['supplier', 'contractor', 'service', 'other'])],
            'form.contact_person' => ['required', 'string', 'max:180'],
            'form.phone' => ['required', 'string', 'max:50'],
            'form.email' => ['required', 'email', 'max:255'],
            'form.address' => ['required', 'string', 'max:1000'],
            'form.bank_name' => ['required', 'string', 'max:180'],
            'form.account_name' => ['required', 'string', 'max:180'],
            'form.account_number' => ['required', 'string', 'max:80'],
            'form.notes' => ['required', 'string', 'max:2000'],
            'form.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.name.required' => 'Vendor name is required.',
            'form.vendor_type.required' => 'Vendor type is required.',
            'form.contact_person.required' => 'Contact person is required.',
            'form.phone.required' => 'Phone is required.',
            'form.email.required' => 'Email is required.',
            'form.address.required' => 'Address is required.',
            'form.bank_name.required' => 'Bank name is required.',
            'form.account_name.required' => 'Account name is required.',
            'form.account_number.required' => 'Account number is required.',
            'form.notes.required' => 'Notes are required.',
            'form.is_active.required' => 'Status is required.',
            'form.vendor_type.in' => 'Please select a valid vendor type.',
            'form.email.email' => 'Please enter a valid email address.',
            'form.name.max' => 'Vendor name cannot exceed 180 characters.',
            'form.contact_person.max' => 'Contact person cannot exceed 180 characters.',
            'form.phone.max' => 'Phone cannot exceed 50 characters.',
            'form.email.max' => 'Email cannot exceed 255 characters.',
            'form.address.max' => 'Address cannot exceed 1000 characters.',
            'form.bank_name.max' => 'Bank name cannot exceed 180 characters.',
            'form.account_name.max' => 'Account name cannot exceed 180 characters.',
            'form.account_number.max' => 'Account number cannot exceed 80 characters.',
            'form.notes.max' => 'Notes cannot exceed 2000 characters.',
        ];
    }
}
