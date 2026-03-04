<?php

namespace App\Services\Procurement;

use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use Illuminate\Database\QueryException;

class ProcurementControlSettingsService
{
    /**
     * @var array<int, CompanyProcurementControlSetting>
     */
    private array $settingsCache = [];

    public function settingsForCompany(?int $companyId): CompanyProcurementControlSetting
    {
        $resolvedCompanyId = $this->resolveCompanyId($companyId);

        if (isset($this->settingsCache[$resolvedCompanyId])) {
            return $this->settingsCache[$resolvedCompanyId];
        }

        $existing = CompanyProcurementControlSetting::query()
            ->where('company_id', $resolvedCompanyId)
            ->first();

        if ($existing instanceof CompanyProcurementControlSetting) {
            $this->settingsCache[$resolvedCompanyId] = $existing;

            return $existing;
        }

        try {
            $setting = CompanyProcurementControlSetting::query()->create([
                'company_id' => $resolvedCompanyId,
                ...CompanyProcurementControlSetting::defaultAttributes(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCompanySettingsInsert($exception)) {
                throw $exception;
            }

            // Concurrent requests can both attempt defaults bootstrap; fetch the row that won the race.
            $setting = CompanyProcurementControlSetting::query()
                ->where('company_id', $resolvedCompanyId)
                ->firstOrFail();
        }

        $this->settingsCache[$resolvedCompanyId] = $setting;

        return $setting;
    }

    /**
     * @return array{
     *   conversion_allowed_statuses:array<int,string>,
     *   require_vendor_on_conversion:bool,
     *   default_expected_delivery_days:int,
     *   auto_post_commitment_on_issue:bool,
     *   issue_allowed_roles:array<int,string>,
     *   receipt_allowed_roles:array<int,string>,
     *   invoice_link_allowed_roles:array<int,string>,
     *   allow_over_receipt:bool,
     *   match_amount_tolerance_percent:float,
     *   match_quantity_tolerance_percent:float,
     *   match_date_tolerance_days:int,
     *   block_payment_on_mismatch:bool,
     *   match_override_allowed_roles:array<int,string>,
     *   mandatory_po_enabled:bool,
     *   mandatory_po_min_amount:int,
     *   mandatory_po_category_codes:array<int,string>,
     *   match_override_requires_maker_checker:bool,
     *   stale_commitment_alert_age_hours:int,
     *   stale_commitment_alert_count_threshold:int
     * }
     */
    public function effectiveControls(?int $companyId): array
    {
        $setting = $this->settingsForCompany($companyId);
        $defaults = CompanyProcurementControlSetting::defaultControls();
        $configured = (array) ($setting->controls ?? []);

        $statuses = array_values(array_filter(array_map(
            static fn (mixed $status): string => strtolower(trim((string) $status)),
            (array) ($configured['conversion_allowed_statuses'] ?? $defaults['conversion_allowed_statuses'])
        )));

        if ($statuses === []) {
            $statuses = (array) $defaults['conversion_allowed_statuses'];
        }

        // Keep conversion usable after final approval moves into execution-ready status.
        if (in_array('approved', $statuses, true) && ! in_array('approved_for_execution', $statuses, true)) {
            $statuses[] = 'approved_for_execution';
        }

        $statuses = array_values(array_unique($statuses));

        $issueRoles = $this->sanitizeRoles(
            (array) ($configured['issue_allowed_roles'] ?? $defaults['issue_allowed_roles']),
            (array) $defaults['issue_allowed_roles']
        );

        $receiptRoles = $this->sanitizeRoles(
            (array) ($configured['receipt_allowed_roles'] ?? $defaults['receipt_allowed_roles']),
            (array) $defaults['receipt_allowed_roles']
        );

        $invoiceLinkRoles = $this->sanitizeRoles(
            (array) ($configured['invoice_link_allowed_roles'] ?? $defaults['invoice_link_allowed_roles']),
            (array) $defaults['invoice_link_allowed_roles']
        );

        $overrideRoles = $this->sanitizeRoles(
            (array) ($configured['match_override_allowed_roles'] ?? $defaults['match_override_allowed_roles']),
            (array) $defaults['match_override_allowed_roles']
        );

        $mandatoryPoCategories = $this->sanitizeCategoryCodes(
            (array) ($configured['mandatory_po_category_codes'] ?? $defaults['mandatory_po_category_codes']),
            (array) $defaults['mandatory_po_category_codes']
        );

        return [
            'conversion_allowed_statuses' => $statuses,
            'require_vendor_on_conversion' => (bool) ($configured['require_vendor_on_conversion'] ?? $defaults['require_vendor_on_conversion']),
            'default_expected_delivery_days' => max(1, (int) ($configured['default_expected_delivery_days'] ?? $defaults['default_expected_delivery_days'])),
            'auto_post_commitment_on_issue' => (bool) ($configured['auto_post_commitment_on_issue'] ?? $defaults['auto_post_commitment_on_issue']),
            'issue_allowed_roles' => $issueRoles,
            'receipt_allowed_roles' => $receiptRoles,
            'invoice_link_allowed_roles' => $invoiceLinkRoles,
            'allow_over_receipt' => (bool) ($configured['allow_over_receipt'] ?? $defaults['allow_over_receipt']),
            'match_amount_tolerance_percent' => max(0, (float) ($configured['match_amount_tolerance_percent'] ?? $defaults['match_amount_tolerance_percent'])),
            'match_quantity_tolerance_percent' => max(0, (float) ($configured['match_quantity_tolerance_percent'] ?? $defaults['match_quantity_tolerance_percent'])),
            'match_date_tolerance_days' => max(0, (int) ($configured['match_date_tolerance_days'] ?? $defaults['match_date_tolerance_days'])),
            'block_payment_on_mismatch' => (bool) ($configured['block_payment_on_mismatch'] ?? $defaults['block_payment_on_mismatch']),
            'match_override_allowed_roles' => $overrideRoles,
            'mandatory_po_enabled' => (bool) ($configured['mandatory_po_enabled'] ?? $defaults['mandatory_po_enabled']),
            'mandatory_po_min_amount' => max(0, (int) ($configured['mandatory_po_min_amount'] ?? $defaults['mandatory_po_min_amount'])),
            'mandatory_po_category_codes' => $mandatoryPoCategories,
            'match_override_requires_maker_checker' => (bool) ($configured['match_override_requires_maker_checker'] ?? $defaults['match_override_requires_maker_checker']),
            'stale_commitment_alert_age_hours' => max(1, (int) ($configured['stale_commitment_alert_age_hours'] ?? $defaults['stale_commitment_alert_age_hours'])),
            'stale_commitment_alert_count_threshold' => max(1, (int) ($configured['stale_commitment_alert_count_threshold'] ?? $defaults['stale_commitment_alert_count_threshold'])),
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

    /**
     * @param  array<int, mixed>  $codes
     * @param  array<int, mixed>  $fallback
     * @return array<int, string>
     */
    private function sanitizeCategoryCodes(array $codes, array $fallback): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $code): string => strtolower(trim((string) $code)),
            $codes
        )));

        if ($normalized !== []) {
            return $normalized;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $code): string => strtolower(trim((string) $code)),
            $fallback
        )));
    }

    private function resolveCompanyId(?int $companyId): int
    {
        $resolved = (int) ($companyId ?? 0);

        if ($resolved <= 0) {
            $resolved = (int) (auth()->user()?->company_id ?? 0);
        }

        if ($resolved <= 0) {
            throw new \RuntimeException('Unable to resolve procurement settings company scope.');
        }

        return $resolved;
    }

    private function isDuplicateCompanySettingsInsert(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $driverCode === 1062 || $sqlState === '23000';
    }
}

