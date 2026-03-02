<?php

namespace App\Livewire\Settings;

use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use App\Enums\UserRole;
use App\Services\Procurement\ProcurementControlSettingsService;
use App\Services\TenantAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Procurement Controls')]
class ProcurementControlsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /**
     * @var array{
     *   conversion_allowed_statuses:string,
     *   require_vendor_on_conversion:bool,
     *   default_expected_delivery_days:string,
     *   auto_post_commitment_on_issue:bool,
     *   issue_allowed_roles:array<int,string>,
     *   receipt_allowed_roles:array<int,string>,
     *   invoice_link_allowed_roles:array<int,string>,
     *   allow_over_receipt:bool
     * }
     */
    public array $controlsForm = [];

    public function mount(ProcurementControlSettingsService $settingsService): void
    {
        $this->authorizeOwner();
        $this->hydrateFromSetting($settingsService);
    }

    public function save(
        ProcurementControlSettingsService $settingsService,
        TenantAuditLogger $tenantAuditLogger
    ): void {
        $this->authorizeOwner();

        $validated = $this->validate([
            'controlsForm.conversion_allowed_statuses' => ['required', 'string', 'max:255'],
            'controlsForm.require_vendor_on_conversion' => ['boolean'],
            'controlsForm.default_expected_delivery_days' => ['required', 'integer', 'min:1', 'max:365'],
            'controlsForm.auto_post_commitment_on_issue' => ['boolean'],
            'controlsForm.issue_allowed_roles' => ['required', 'array', 'min:1'],
            'controlsForm.issue_allowed_roles.*' => ['string', Rule::in(UserRole::values())],
            'controlsForm.receipt_allowed_roles' => ['required', 'array', 'min:1'],
            'controlsForm.receipt_allowed_roles.*' => ['string', Rule::in(UserRole::values())],
            'controlsForm.invoice_link_allowed_roles' => ['required', 'array', 'min:1'],
            'controlsForm.invoice_link_allowed_roles.*' => ['string', Rule::in(UserRole::values())],
            'controlsForm.allow_over_receipt' => ['boolean'],
        ]);

        $statuses = array_values(array_filter(array_map(
            static fn (string $status): string => strtolower(trim($status)),
            explode(',', (string) $validated['controlsForm']['conversion_allowed_statuses'])
        )));

        if ($statuses === []) {
            $this->addError('controlsForm.conversion_allowed_statuses', 'Provide at least one allowed request status.');

            return;
        }

        $setting = $settingsService->settingsForCompany((int) auth()->user()->company_id);

        $controls = [
            'conversion_allowed_statuses' => $statuses,
            'require_vendor_on_conversion' => (bool) $validated['controlsForm']['require_vendor_on_conversion'],
            'default_expected_delivery_days' => (int) $validated['controlsForm']['default_expected_delivery_days'],
            'auto_post_commitment_on_issue' => (bool) $validated['controlsForm']['auto_post_commitment_on_issue'],
            'issue_allowed_roles' => array_values((array) $validated['controlsForm']['issue_allowed_roles']),
            'receipt_allowed_roles' => array_values((array) $validated['controlsForm']['receipt_allowed_roles']),
            'invoice_link_allowed_roles' => array_values((array) $validated['controlsForm']['invoice_link_allowed_roles']),
            'allow_over_receipt' => (bool) $validated['controlsForm']['allow_over_receipt'],
        ];

        $setting->forceFill([
            'controls' => $controls,
            'updated_by' => (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: (int) auth()->user()->company_id,
            action: 'tenant.procurement.controls.updated',
            actor: auth()->user(),
            description: 'Procurement control settings updated from tenant settings page.',
            entityType: CompanyProcurementControlSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'controls' => $controls,
            ],
        );

        $this->setFeedback('Procurement controls updated.');
    }

    public function resetToDefault(ProcurementControlSettingsService $settingsService): void
    {
        $this->authorizeOwner();

        $setting = $settingsService->settingsForCompany((int) auth()->user()->company_id);
        $setting->forceFill([
            'controls' => CompanyProcurementControlSetting::defaultControls(),
            'updated_by' => (int) auth()->id(),
        ])->save();

        $this->hydrateFromSetting($settingsService);
        $this->setFeedback('Procurement controls reset to defaults.');
    }

    public function render(): View
    {
        return view('livewire.settings.procurement-controls-page', [
            'roles' => UserRole::values(),
        ]);
    }

    private function hydrateFromSetting(ProcurementControlSettingsService $settingsService): void
    {
        $controls = $settingsService->effectiveControls((int) auth()->user()->company_id);

        $this->controlsForm = [
            'conversion_allowed_statuses' => implode(', ', (array) $controls['conversion_allowed_statuses']),
            'require_vendor_on_conversion' => (bool) $controls['require_vendor_on_conversion'],
            'default_expected_delivery_days' => (string) ((int) $controls['default_expected_delivery_days']),
            'auto_post_commitment_on_issue' => (bool) $controls['auto_post_commitment_on_issue'],
            'issue_allowed_roles' => array_values((array) $controls['issue_allowed_roles']),
            'receipt_allowed_roles' => array_values((array) $controls['receipt_allowed_roles']),
            'invoice_link_allowed_roles' => array_values((array) $controls['invoice_link_allowed_roles']),
            'allow_over_receipt' => (bool) $controls['allow_over_receipt'],
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
            throw new AuthorizationException('Only admin (owner) can manage procurement controls.');
        }
    }
}