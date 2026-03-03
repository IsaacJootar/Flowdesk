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
     *   out_of_pocket_requires_reimbursement_link:bool
     * }
     */
    public function effectiveControls(int $companyId): array
    {
        $setting = $this->settingsForCompany($companyId);
        $defaults = CompanyTreasuryControlSetting::defaultControls();
        $configured = (array) ($setting->controls ?? []);

        return [
            'statement_import_max_rows' => max(100, (int) ($configured['statement_import_max_rows'] ?? $defaults['statement_import_max_rows'])),
            'auto_match_date_window_days' => max(0, (int) ($configured['auto_match_date_window_days'] ?? $defaults['auto_match_date_window_days'])),
            'auto_match_amount_tolerance' => max(0, (int) ($configured['auto_match_amount_tolerance'] ?? $defaults['auto_match_amount_tolerance'])),
            'auto_match_min_confidence' => max(1, min(99, (int) ($configured['auto_match_min_confidence'] ?? $defaults['auto_match_min_confidence']))),
            'direct_expense_text_similarity_threshold' => max(0, min(100, (int) ($configured['direct_expense_text_similarity_threshold'] ?? $defaults['direct_expense_text_similarity_threshold']))),
            'exception_alert_age_hours' => max(1, (int) ($configured['exception_alert_age_hours'] ?? $defaults['exception_alert_age_hours'])),
            'out_of_pocket_requires_reimbursement_link' => (bool) ($configured['out_of_pocket_requires_reimbursement_link'] ?? $defaults['out_of_pocket_requires_reimbursement_link']),
        ];
    }
}
