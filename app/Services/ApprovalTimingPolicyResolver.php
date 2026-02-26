<?php

namespace App\Services;

use App\Domains\Approvals\Models\CompanyApprovalTimingSetting;
use App\Domains\Approvals\Models\DepartmentApprovalTimingOverride;
use Illuminate\Support\Facades\Auth;

class ApprovalTimingPolicyResolver
{
    public const MIN_STEP_DUE_HOURS = 1;
    public const MAX_STEP_DUE_HOURS = 720;
    public const MIN_REMINDER_HOURS_BEFORE_DUE = 0;
    public const MAX_ESCALATION_GRACE_HOURS = 720;

    public function settingsForCompany(int $companyId): CompanyApprovalTimingSetting
    {
        return CompanyApprovalTimingSetting::query()
            ->firstOrCreate(
                ['company_id' => $companyId],
                array_merge(
                    CompanyApprovalTimingSetting::defaultAttributes(),
                    ['created_by' => Auth::id()]
                )
            );
    }

    /**
     * @return array<int, DepartmentApprovalTimingOverride>
     */
    public function overridesForCompany(int $companyId): array
    {
        return DepartmentApprovalTimingOverride::query()
            ->where('company_id', $companyId)
            ->orderBy('department_id')
            ->get()
            ->all();
    }

    /**
     * Resolve final timing values.
     *
     * Precedence:
     * 1) explicit step-level values (metadata snapshot)
     * 2) department override
     * 3) organization default
     * 4) config fallback (inside default values)
     *
     * @param  array<string, mixed>  $stepLevelSla
     * @return array{step_due_hours:int, reminder_hours_before_due:int, escalation_grace_hours:int, source:string}
     */
    public function resolve(int $companyId, ?int $departmentId = null, array $stepLevelSla = []): array
    {
        $explicit = $this->normalizeSla($stepLevelSla);
        if ($explicit !== null) {
            return $explicit + ['source' => 'step'];
        }

        if ($departmentId) {
            $override = DepartmentApprovalTimingOverride::query()
                ->where('company_id', $companyId)
                ->where('department_id', $departmentId)
                ->first();

            if ($override) {
                return $this->guardrail([
                    'step_due_hours' => (int) $override->step_due_hours,
                    'reminder_hours_before_due' => (int) $override->reminder_hours_before_due,
                    'escalation_grace_hours' => (int) $override->escalation_grace_hours,
                ]) + ['source' => 'department'];
            }
        }

        $settings = $this->settingsForCompany($companyId);

        return $this->guardrail([
            'step_due_hours' => (int) $settings->step_due_hours,
            'reminder_hours_before_due' => (int) $settings->reminder_hours_before_due,
            'escalation_grace_hours' => (int) $settings->escalation_grace_hours,
        ]) + ['source' => 'organization'];
    }

    /**
     * @param  array<string, mixed>  $sla
     * @return array{step_due_hours:int, reminder_hours_before_due:int, escalation_grace_hours:int}|null
     */
    private function normalizeSla(array $sla): ?array
    {
        $stepDue = $this->numericOrNull($sla['step_due_hours'] ?? null);
        $reminder = $this->numericOrNull($sla['reminder_hours_before_due'] ?? null);
        $escalation = $this->numericOrNull($sla['escalation_grace_hours'] ?? null);

        if ($stepDue === null || $reminder === null || $escalation === null) {
            return null;
        }

        return $this->guardrail([
            'step_due_hours' => $stepDue,
            'reminder_hours_before_due' => $reminder,
            'escalation_grace_hours' => $escalation,
        ]);
    }

    /**
     * @param  array{step_due_hours:int, reminder_hours_before_due:int, escalation_grace_hours:int}  $input
     * @return array{step_due_hours:int, reminder_hours_before_due:int, escalation_grace_hours:int}
     */
    public function guardrail(array $input): array
    {
        $stepDue = max(self::MIN_STEP_DUE_HOURS, min(self::MAX_STEP_DUE_HOURS, (int) $input['step_due_hours']));
        $reminder = max(self::MIN_REMINDER_HOURS_BEFORE_DUE, min($stepDue, (int) $input['reminder_hours_before_due']));
        $escalation = max(0, min(self::MAX_ESCALATION_GRACE_HOURS, (int) $input['escalation_grace_hours']));

        return [
            'step_due_hours' => $stepDue,
            'reminder_hours_before_due' => $reminder,
            'escalation_grace_hours' => $escalation,
        ];
    }

    private function numericOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
