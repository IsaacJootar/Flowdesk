<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Enums\UserRole;
use App\Services\ExpensePolicyResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Expense Controls')]
class ExpenseControlsPage extends Component
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

    public function mount(ExpensePolicyResolver $expensePolicyResolver): void
    {
        $this->authorizeOwner();
        $this->roles = UserRole::values();
        $this->hydrateFromSetting($expensePolicyResolver);
    }

    /**
     * @throws ValidationException
     */
    public function save(ExpensePolicyResolver $expensePolicyResolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $rules = [];
        foreach (CompanyExpensePolicySetting::ACTIONS as $action) {
            $rules["actionPolicies.{$action}.allowed_roles"] = ['required', 'array', 'min:1'];
            $rules["actionPolicies.{$action}.allowed_roles.*"] = ['string', Rule::in(UserRole::values())];
            $rules["actionPolicies.{$action}.department_ids"] = ['nullable', 'array'];
            $rules["actionPolicies.{$action}.department_ids.*"] = [
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('company_id', (int) \Illuminate\Support\Facades\Auth::user()->company_id)->whereNull('deleted_at')
                ),
            ];
            $rules["actionPolicies.{$action}.require_secondary_approval_over_limit"] = ['boolean'];
            foreach (UserRole::values() as $role) {
                $rules["actionPolicies.{$action}.amount_limits.{$role}"] = ['nullable', 'integer', 'min:1'];
            }
        }

        $validated = $this->validate($rules);
        $normalized = [];
        foreach (CompanyExpensePolicySetting::ACTIONS as $action) {
            $data = (array) ($validated['actionPolicies'][$action] ?? []);
            $allowedRoles = array_values(array_unique(array_map('strval', (array) ($data['allowed_roles'] ?? []))));
            $amountLimits = $this->normalizeAmountLimits((array) ($data['amount_limits'] ?? []));
            $invalidLimitRoles = array_values(array_diff(array_keys($amountLimits), $allowedRoles));
            if ($invalidLimitRoles !== []) {
                throw ValidationException::withMessages([
                    "actionPolicies.$action.amount_limits" => sprintf(
                        'Amount limits can only be set for allowed roles. Remove limits for: %s.',
                        implode(', ', array_map('ucfirst', $invalidLimitRoles))
                    ),
                ]);
            }

            $normalized[$action] = [
                'allowed_roles' => $allowedRoles,
                'department_ids' => array_values(array_unique(array_filter(
                    array_map('intval', (array) ($data['department_ids'] ?? [])),
                    fn (int $id): bool => $id > 0
                ))),
                'amount_limits' => $amountLimits,
                'require_secondary_approval_over_limit' => (bool) ($data['require_secondary_approval_over_limit'] ?? false),
            ];
        }

        $setting = $expensePolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $normalized,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $normalized;
        $this->setFeedback('Expense permission controls updated.');
    }

    public function resetToDefault(ExpensePolicyResolver $expensePolicyResolver): void
    {
        $this->authorizeOwner();

        $defaults = CompanyExpensePolicySetting::defaultActionPolicies();
        $setting = $expensePolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'action_policies' => $defaults,
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->actionPolicies = $defaults;
        $this->setFeedback('Expense controls reset to default (Owner + Finance).');
    }

    public function render(): View
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.settings.expense-controls-page', [
            'departments' => $departments,
            'actionDefinitions' => $this->actionDefinitions(),
            'roles' => UserRole::values(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function actionDefinitions(): array
    {
        return [
            CompanyExpensePolicySetting::ACTION_CREATE_DIRECT => 'Create direct expense',
            CompanyExpensePolicySetting::ACTION_CREATE_FROM_REQUEST => 'Create expense from approved request',
            CompanyExpensePolicySetting::ACTION_EDIT_POSTED => 'Edit posted expense',
            CompanyExpensePolicySetting::ACTION_VOID => 'Void expense',
        ];
    }

    /**
     * @param  array<string, mixed>  $limits
     * @return array<string, int>
     */
    private function normalizeAmountLimits(array $limits): array
    {
        $normalized = [];
        foreach (UserRole::values() as $role) {
            $value = $limits[$role] ?? null;
            $value = is_numeric($value) ? (int) $value : 0;
            if ($value > 0) {
                $normalized[$role] = $value;
            }
        }

        return $normalized;
    }

    private function hydrateFromSetting(ExpensePolicyResolver $expensePolicyResolver): void
    {
        $setting = $expensePolicyResolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $actionPolicies = [];
        foreach (CompanyExpensePolicySetting::ACTIONS as $action) {
            $policy = $setting->policyForAction($action);

            $amountLimits = [];
            foreach (UserRole::values() as $role) {
                $amountLimits[$role] = (string) ((int) (($policy['amount_limits'][$role] ?? 0)));
                if ($amountLimits[$role] === '0') {
                    $amountLimits[$role] = '';
                }
            }

            $actionPolicies[$action] = [
                'allowed_roles' => array_values((array) ($policy['allowed_roles'] ?? [])),
                'department_ids' => array_values(array_map('intval', (array) ($policy['department_ids'] ?? []))),
                'amount_limits' => $amountLimits,
                'require_secondary_approval_over_limit' => (bool) ($policy['require_secondary_approval_over_limit'] ?? false),
            ];
        }

        $this->actionPolicies = $actionPolicies;
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
            throw new AuthorizationException('Only admin (owner) can manage expense controls.');
        }
    }
}
