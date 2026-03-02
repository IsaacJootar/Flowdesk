<?php

namespace App\Services\Procurement;

use App\Domains\Procurement\Models\CompanyProcurementControlSetting;

class ProcurementControlSettingsService
{
    public function settingsForCompany(int $companyId): CompanyProcurementControlSetting
    {
        return CompanyProcurementControlSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            CompanyProcurementControlSetting::defaultAttributes(),
        );
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
     *   allow_over_receipt:bool
     * }
     */
    public function effectiveControls(int $companyId): array
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

        return [
            'conversion_allowed_statuses' => $statuses,
            'require_vendor_on_conversion' => (bool) ($configured['require_vendor_on_conversion'] ?? $defaults['require_vendor_on_conversion']),
            'default_expected_delivery_days' => max(1, (int) ($configured['default_expected_delivery_days'] ?? $defaults['default_expected_delivery_days'])),
            'auto_post_commitment_on_issue' => (bool) ($configured['auto_post_commitment_on_issue'] ?? $defaults['auto_post_commitment_on_issue']),
            'issue_allowed_roles' => $issueRoles,
            'receipt_allowed_roles' => $receiptRoles,
            'invoice_link_allowed_roles' => $invoiceLinkRoles,
            'allow_over_receipt' => (bool) ($configured['allow_over_receipt'] ?? $defaults['allow_over_receipt']),
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