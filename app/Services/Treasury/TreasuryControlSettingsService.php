<?php

namespace App\Services\Treasury;

use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;

class TreasuryControlSettingsService
{
    public function settingsForCompany(int $companyId): CompanyTreasuryControlSetting
    {
        return CompanyTreasuryControlSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            CompanyTreasuryControlSetting::defaultAttributes(),
        );
    }

    /**
     * @return array{
     *   statement_import_max_rows:int,
     *   auto_match_date_window_days:int,
     *   auto_match_amount_tolerance:int,
     *   auto_match_min_confidence:int,
     *   direct_expense_text_similarity_threshold:int,
     *   exception_alert_age_hours:int,
     *   reconciliation_backlog_alert_count_threshold:int,
     *   exception_action_allowed_roles:array<int,string>,
     *   exception_action_requires_maker_checker:bool,
     *   out_of_pocket_requires_reimbursement_link:bool
     * }
     */
    public function effectiveControls(int $companyId): array
    {
        $setting = $this->settingsForCompany($companyId);
        $defaults = CompanyTreasuryControlSetting::defaultControls();
        $configured = (array) ($setting->controls ?? []);

        $exceptionActionAllowedRoles = $this->sanitizeRoles(
            (array) ($configured['exception_action_allowed_roles'] ?? $defaults['exception_action_allowed_roles']),
            (array) $defaults['exception_action_allowed_roles']
        );

        return [
            'statement_import_max_rows' => max(100, (int) ($configured['statement_import_max_rows'] ?? $defaults['statement_import_max_rows'])),
            'auto_match_date_window_days' => max(0, (int) ($configured['auto_match_date_window_days'] ?? $defaults['auto_match_date_window_days'])),
            'auto_match_amount_tolerance' => max(0, (int) ($configured['auto_match_amount_tolerance'] ?? $defaults['auto_match_amount_tolerance'])),
            'auto_match_min_confidence' => max(1, min(99, (int) ($configured['auto_match_min_confidence'] ?? $defaults['auto_match_min_confidence']))),
            'direct_expense_text_similarity_threshold' => max(0, min(100, (int) ($configured['direct_expense_text_similarity_threshold'] ?? $defaults['direct_expense_text_similarity_threshold']))),
            'exception_alert_age_hours' => max(1, (int) ($configured['exception_alert_age_hours'] ?? $defaults['exception_alert_age_hours'])),
            'reconciliation_backlog_alert_count_threshold' => max(1, (int) ($configured['reconciliation_backlog_alert_count_threshold'] ?? $defaults['reconciliation_backlog_alert_count_threshold'])),
            'exception_action_allowed_roles' => $exceptionActionAllowedRoles,
            'exception_action_requires_maker_checker' => (bool) ($configured['exception_action_requires_maker_checker'] ?? $defaults['exception_action_requires_maker_checker']),
            'out_of_pocket_requires_reimbursement_link' => (bool) ($configured['out_of_pocket_requires_reimbursement_link'] ?? $defaults['out_of_pocket_requires_reimbursement_link']),
        ];
    }

    /**
     * @param  array<int, mixed>  $roles
     * @param  array<int, mixed>  $fallback
     * @return array<int, string>
     */
    private function sanitizeRoles(array $roles, array $fallback): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            $roles
        )));

        if ($normalized !== []) {
            return $normalized;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            $fallback
        )));
    }
}
