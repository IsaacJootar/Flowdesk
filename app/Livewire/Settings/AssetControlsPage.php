<?php

namespace App\Livewire\Settings;

use App\Domains\Assets\Models\CompanyAssetPolicySetting;
use App\Enums\UserRole;
use App\Services\AssetPolicyResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Asset Rules')]
class AssetControlsPage extends Component
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

    public function mount(AssetPolicyResolver $assetPolicyResolver): void
    {
        $this->authorizeOwner();
        $this->roles = UserRole::values();
        $this->hydrateFromSetting($assetPolicyResolver);
    }

    /**
     * @throws ValidationException
     */
    public function save(AssetPolicyResolver $assetPolicyResolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $rules = [];
        foreach (CompanyAssetPolicySetting::ACTIONS as $action) {
            $rules["actionPolicies.{$action}.allowed_roles"] = ['required', 'array', 'min:1'];
            $rules["actionPolicies.{$action}.allowed_roles.*"] = ['string', Rule::in(UserRole::values())];
        }

        $validated = $this->validate($rules);
        $normalized = [];

        foreach (CompanyAssetPolicySetting::ACTIONS as $action) {
            $data = (array) ($validated['actionPolicies'][$action] ?? []);
            $allowedRoles = array_values(array_unique(array_map('strval', (array) ($data['allowed_roles'] ?? []))));
            if ($allowedRoles === []) {
                $allowedRoles = (array) (CompanyAssetPolicySetting::defaultActionPolicies()[$action]['allowed_roles'] ?? []);
            }

            $normalized[$action] = [
                'allowed_roles' => $allowedRoles,
            ];
        }

        $setting = $assetPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $normalized,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $normalized;
        $this->setFeedback('Asset controls updated.');
    }

    public function resetToDefault(AssetPolicyResolver $assetPolicyResolver): void
    {
        $this->authorizeOwner();

        $defaults = CompanyAssetPolicySetting::defaultActionPolicies();
        $setting = $assetPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $defaults,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $defaults;
        $this->setFeedback('Asset controls reset to default.');
    }

    public function render(): View
    {
        return view('livewire.settings.asset-controls-page', [
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
            CompanyAssetPolicySetting::ACTION_VIEW_REGISTRY => 'View asset registry',
            CompanyAssetPolicySetting::ACTION_REGISTER_ASSET => 'Register asset',
            CompanyAssetPolicySetting::ACTION_EDIT_ASSET => 'Edit asset profile',
            CompanyAssetPolicySetting::ACTION_ASSIGN_TRANSFER_RETURN => 'Assign, transfer, and return asset',
            CompanyAssetPolicySetting::ACTION_LOG_MAINTENANCE => 'Log maintenance',
            CompanyAssetPolicySetting::ACTION_DISPOSE_ASSET => 'Dispose asset',
        ];
    }

    private function hydrateFromSetting(AssetPolicyResolver $assetPolicyResolver): void
    {
        $setting = $assetPolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $policies = [];

        foreach (CompanyAssetPolicySetting::ACTIONS as $action) {
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
            throw new AuthorizationException('Only admin (owner) can manage asset controls.');
        }
    }
}

