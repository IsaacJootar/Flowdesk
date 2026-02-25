<?php

namespace App\Livewire\Settings;

use App\Actions\Company\CreateCompanyForUser;
use App\Domains\Company\Models\Company;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
#[Title('Company Setup')]
class CompanySetup extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $name = '';
    public string $slug = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $industry = null;
    public string $currency_code = 'NGN';
    public string $timezone = 'Africa/Lagos';
    public ?string $address = null;
    public bool $isEditMode = false;
    public ?int $companyId = null;

    public function mount(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if (! $user) {
            return;
        }

        $this->email = $user->email;

        if (! $user->company_id) {
            return;
        }

        $company = Company::query()->find($user->company_id);

        if (! $company) {
            return;
        }

        $this->isEditMode = true;
        $this->companyId = (int) $company->id;
        $this->name = (string) $company->name;
        $this->slug = (string) $company->slug;
        $this->email = $company->email ?: $user->email;
        $this->phone = $company->phone;
        $this->industry = $company->industry;
        $this->currency_code = (string) ($company->currency_code ?: 'NGN');
        $this->timezone = (string) ($company->timezone ?: 'Africa/Lagos');
        $this->address = $company->address;
    }

    /**
     * @throws ValidationException
     */
    public function save(CreateCompanyForUser $createCompanyForUser): void
    {
        $this->feedbackError = null;

        $slugRules = ['nullable', 'string', 'max:120'];

        if ($this->isEditMode && $this->companyId) {
            $slugRules[] = Rule::unique('companies', 'slug')->ignore($this->companyId);
        } else {
            $slugRules[] = Rule::unique('companies', 'slug');
        }

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => $slugRules,
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        if ($this->isEditMode) {
            if (! $user || ! $user->hasRole(UserRole::Owner)) {
                throw ValidationException::withMessages([
                    'name' => 'Only admin (owner) can update company settings.',
                ]);
            }

            $company = Company::query()
                ->whereKey($this->companyId)
                ->where('id', $user->company_id)
                ->firstOrFail();

            $company->forceFill([
                'name' => $this->name,
                'slug' => $this->resolveUniqueSlug($this->slug !== '' ? $this->slug : $this->name, $company->id),
                'email' => $this->email,
                'phone' => $this->phone,
                'industry' => $this->industry,
                'currency_code' => strtoupper($this->currency_code),
                'timezone' => $this->timezone,
                'address' => $this->address,
                'updated_by' => $user->id,
            ])->save();

            $company->refresh();

            $this->name = (string) $company->name;
            $this->slug = (string) $company->slug;
            $this->email = $company->email;
            $this->phone = $company->phone;
            $this->industry = $company->industry;
            $this->currency_code = (string) ($company->currency_code ?: 'NGN');
            $this->timezone = (string) ($company->timezone ?: 'Africa/Lagos');
            $this->address = $company->address;

            if ($user) {
                $user->setRelation('company', $company);
            }

            $this->dispatch('company-name-updated', name: (string) $company->name);
            $this->setFeedback('Company settings updated.');

            return;
        }

        $createCompanyForUser(\Illuminate\Support\Facades\Auth::user(), [
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'industry' => $this->industry,
            'currency_code' => strtoupper($this->currency_code),
            'timezone' => $this->timezone,
            'address' => $this->address,
        ]);

        session()->flash('status', 'Company setup complete. General department created.');
        $this->redirectRoute('dashboard');
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function resolveUniqueSlug(string $value, ?int $ignoreCompanyId = null): string
    {
        $baseSlug = Str::slug($value);
        $rootSlug = $baseSlug !== '' ? $baseSlug : 'company';
        $slug = $rootSlug;
        $counter = 1;

        while (
            Company::query()
                ->when($ignoreCompanyId, fn ($query) => $query->whereKeyNot($ignoreCompanyId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $rootSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.settings.company-setup')
            ->layoutData([
                'subtitle' => $this->isEditMode
                    ? 'Review and update your company configuration'
                    : 'Create your company and baseline department',
            ]);
    }
}

