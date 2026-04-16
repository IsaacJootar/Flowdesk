<?php

namespace App\Livewire\Settings;

use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;
use App\Enums\UserRole;
use App\Services\TenantAuditLogger;
use App\Services\Treasury\TreasuryControlSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Treasury Controls')]
class TreasuryControlsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /**
     * @var array{statement_import_max_rows:string,auto_match_date_window_days:string,auto_match_amount_tolerance:string,auto_match_min_confidence:string,direct_expense_text_similarity_threshold:string,exception_alert_age_hours:string,reconciliation_backlog_alert_count_threshold:string,exception_action_allowed_roles:array<int,string>,exception_action_requires_maker_checker:bool,out_of_pocket_requires_reimbursement_link:bool}
     */
    public array $controlsForm = [];

    public function mount(TreasuryControlSettingsService $settingsService): void
    {
        $this->authorizeOwner();
        $this->hydrateFromSetting($settingsService);
    }

    public function save(
        TreasuryControlSettingsService $settingsService,
        TenantAuditLogger $tenantAuditLogger
    ): void {
        $this->authorizeOwner();

        $validated = $this->validate([
            'controlsForm.statement_import_max_rows' => ['required', 'integer', 'min:100', 'max:200000'],
            'controlsForm.auto_match_date_window_days' => ['required', 'integer', 'min:0', 'max:30'],
            'controlsForm.auto_match_amount_tolerance' => ['required', 'integer', 'min:0', 'max:1000000'],
            'controlsForm.auto_match_min_confidence' => ['required', 'integer', 'min:1', 'max:99'],
            'controlsForm.direct_expense_text_similarity_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'controlsForm.exception_alert_age_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'controlsForm.reconciliation_backlog_alert_count_threshold' => ['required', 'integer', 'min:1', 'max:2000'],
            'controlsForm.exception_action_allowed_roles' => ['required', 'array', 'min:1'],
            'controlsForm.exception_action_allowed_roles.*' => ['string', Rule::in(UserRole::values())],
            'controlsForm.exception_action_requires_maker_checker' => ['boolean'],
            'controlsForm.out_of_pocket_requires_reimbursement_link' => ['boolean'],
        ]);

        $controls = [
            'statement_import_max_rows' => (int) $validated['controlsForm']['statement_import_max_rows'],
            'auto_match_date_window_days' => (int) $validated['controlsForm']['auto_match_date_window_days'],
            'auto_match_amount_tolerance' => (int) $validated['controlsForm']['auto_match_amount_tolerance'],
            'auto_match_min_confidence' => (int) $validated['controlsForm']['auto_match_min_confidence'],
            'direct_expense_text_similarity_threshold' => (int) $validated['controlsForm']['direct_expense_text_similarity_threshold'],
            'exception_alert_age_hours' => (int) $validated['controlsForm']['exception_alert_age_hours'],
            'reconciliation_backlog_alert_count_threshold' => (int) $validated['controlsForm']['reconciliation_backlog_alert_count_threshold'],
            'exception_action_allowed_roles' => array_values((array) $validated['controlsForm']['exception_action_allowed_roles']),
            'exception_action_requires_maker_checker' => (bool) ($validated['controlsForm']['exception_action_requires_maker_checker'] ?? true),
            'out_of_pocket_requires_reimbursement_link' => (bool) ($validated['controlsForm']['out_of_pocket_requires_reimbursement_link'] ?? true),
        ];

        $setting = $settingsService->settingsForCompany((int) auth()->user()->company_id);
        $setting->forceFill([
            'controls' => $controls,
            'updated_by' => (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: (int) auth()->user()->company_id,
            action: 'tenant.treasury.controls.updated',
            actor: auth()->user(),
            description: 'Treasury control settings updated from tenant settings page.',
            entityType: CompanyTreasuryControlSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'controls' => $controls,
            ],
        );

        $this->setFeedback('Treasury controls updated.');
    }

    public function resetToDefault(TreasuryControlSettingsService $settingsService): void
    {
        $this->authorizeOwner();

        $setting = $settingsService->settingsForCompany((int) auth()->user()->company_id);
        $setting->forceFill([
            'controls' => CompanyTreasuryControlSetting::defaultControls(),
            'updated_by' => (int) auth()->id(),
        ])->save();

        $this->hydrateFromSetting($settingsService);
        $this->setFeedback('Treasury controls reset to defaults.');
    }

    public function render(): View
    {
        return view('livewire.settings.treasury-controls-page', [
            'roles' => UserRole::values(),
        ]);
    }

    private function hydrateFromSetting(TreasuryControlSettingsService $settingsService): void
    {
        $controls = $settingsService->effectiveControls((int) auth()->user()->company_id);

        $this->controlsForm = [
            'statement_import_max_rows' => (string) ((int) $controls['statement_import_max_rows']),
            'auto_match_date_window_days' => (string) ((int) $controls['auto_match_date_window_days']),
            'auto_match_amount_tolerance' => (string) ((int) $controls['auto_match_amount_tolerance']),
            'auto_match_min_confidence' => (string) ((int) $controls['auto_match_min_confidence']),
            'direct_expense_text_similarity_threshold' => (string) ((int) $controls['direct_expense_text_similarity_threshold']),
            'exception_alert_age_hours' => (string) ((int) $controls['exception_alert_age_hours']),
            'reconciliation_backlog_alert_count_threshold' => (string) ((int) $controls['reconciliation_backlog_alert_count_threshold']),
            'exception_action_allowed_roles' => array_values((array) $controls['exception_action_allowed_roles']),
            'exception_action_requires_maker_checker' => (bool) $controls['exception_action_requires_maker_checker'],
            'out_of_pocket_requires_reimbursement_link' => (bool) $controls['out_of_pocket_requires_reimbursement_link'],
        ];
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function authorizeOwner(): void
    {
        if (! auth()->check() || auth()->user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage treasury controls.');
        }
    }
}
