<?php

namespace App\Livewire\Settings;

use App\Actions\Company\CreateCompanyForUser;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CompanySetup extends Component
{
    public string $name = '';
    public string $slug = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $industry = null;
    public string $currency_code = 'NGN';
    public string $timezone = 'Africa/Lagos';
    public ?string $address = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user && $user->company_id && $user->department_id) {
            $this->redirectRoute('dashboard');
            return;
        }

        $this->email = $user?->email;
    }

    /**
     * @throws ValidationException
     */
    public function save(CreateCompanyForUser $createCompanyForUser): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $createCompanyForUser(auth()->user(), [
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

    public function render()
    {
        return view('livewire.settings.company-setup')
            ->layout('layouts.app', [
                'title' => 'Company Setup',
                'subtitle' => 'Create your company and baseline department',
            ]);
    }
}
