<?php

namespace App\Livewire\Settings;

use App\Domains\Vendors\Models\CompanyVendorPolicySetting;
use App\Enums\UserRole;
use App\Services\VendorPolicyResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Vendor Controls')]
class VendorControlsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $actionPolicies = [];

    /**
     * @var array<int, string>
     */
    public array $roles = [];

    public function mount(VendorPolicyResolver $vendorPolicyResolver): void
    {
        $this->authorizeOwner();
        $this->roles = UserRole::values();
        $this->hydrateFromSetting($vendorPolicyResolver);
    }

    /**
     * @throws ValidationException
     */
    public function save(VendorPolicyResolver $vendorPolicyResolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $rules = [];
        foreach (CompanyVendorPolicySetting::ACTIONS as $action) {
            $rules["actionPolicies.{$action}.allowed_roles"] = ['required', 'array', 'min:1'];
            $rules["actionPolicies.{$action}.allowed_roles.*"] = ['string', Rule::in(UserRole::values())];
        }

        $validated = $this->validate($rules);
        $normalized = [];

        foreach (CompanyVendorPolicySetting::ACTIONS as $action) {
            $data = (array) ($validated['actionPolicies'][$action] ?? []);
            $allowedRoles = array_values(array_unique(array_map('strval', (array) ($data['allowed_roles'] ?? []))));
            if ($allowedRoles === []) {
                $allowedRoles = (array) (CompanyVendorPolicySetting::defaultActionPolicies()[$action]['allowed_roles'] ?? []);
            }

            $normalized[$action] = [
                'allowed_roles' => $allowedRoles,
            ];
        }

        $setting = $vendorPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $normalized,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $normalized;
        $this->setFeedback('Vendor controls updated.');
    }

    public function resetToDefault(VendorPolicyResolver $vendorPolicyResolver): void
    {
        $this->authorizeOwner();

        $defaults = CompanyVendorPolicySetting::defaultActionPolicies();
        $setting = $vendorPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $defaults,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $defaults;
        $this->setFeedback('Vendor controls reset to default (Owner + Finance + Auditor where applicable).');
    }

    public function render(): View
    {
        return view('livewire.settings.vendor-controls-page', [
            'actionDefinitions' => $this->actionDefinitions(),
            'roles' => $this->roles,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function actionDefinitions(): array
    {
        return [
            CompanyVendorPolicySetting::ACTION_VIEW_DIRECTORY => 'View vendor directory',
            CompanyVendorPolicySetting::ACTION_CREATE_VENDOR => 'Create vendor profile',
            CompanyVendorPolicySetting::ACTION_UPDATE_VENDOR => 'Edit vendor profile',
            CompanyVendorPolicySetting::ACTION_DELETE_VENDOR => 'Delete vendor profile',
            CompanyVendorPolicySetting::ACTION_MANAGE_INVOICES => 'Create, edit, and void vendor invoices',
            CompanyVendorPolicySetting::ACTION_RECORD_PAYMENTS => 'Record vendor invoice payments',
            CompanyVendorPolicySetting::ACTION_EXPORT_STATEMENTS => 'Export and print vendor statements',
            CompanyVendorPolicySetting::ACTION_MANAGE_COMMUNICATIONS => 'Run reminder and communication retry operations',
        ];
    }

    private function hydrateFromSetting(VendorPolicyResolver $vendorPolicyResolver): void
    {
        $setting = $vendorPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $policies = [];

        foreach (CompanyVendorPolicySetting::ACTIONS as $action) {
            $policy = $setting->policyForAction($action);
            $policies[$action] = [
                'allowed_roles' => array_values((array) ($policy['allowed_roles'] ?? [])),
            ];
        }

        $this->actionPolicies = $policies;
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function authorizeOwner(): void
    {
        if (! \Illuminate\Support\Facades\Auth::check() || \Illuminate\Support\Facades\Auth::user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage vendor controls.');
        }
    }
}

