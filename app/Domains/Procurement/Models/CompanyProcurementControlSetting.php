<?php

namespace App\Domains\Procurement\Models;

use App\Domains\Company\Models\Company;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProcurementControlSetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'controls',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'controls' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'controls' => self::defaultControls(),
            'metadata' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultControls(): array
    {
        $defaults = (array) config('procurement.defaults', []);

        return [
            'conversion_allowed_statuses' => array_values((array) ($defaults['conversion_allowed_statuses'] ?? ['approved'])),
            'require_vendor_on_conversion' => (bool) ($defaults['require_vendor_on_conversion'] ?? true),
            'default_expected_delivery_days' => max(1, (int) ($defaults['default_expected_delivery_days'] ?? 14)),
            'auto_post_commitment_on_issue' => (bool) ($defaults['auto_post_commitment_on_issue'] ?? true),
            'issue_allowed_roles' => array_values((array) ($defaults['issue_allowed_roles'] ?? ['owner', 'finance'])),
            'receipt_allowed_roles' => array_values((array) ($defaults['receipt_allowed_roles'] ?? ['owner', 'finance', 'manager'])),
            'invoice_link_allowed_roles' => array_values((array) ($defaults['invoice_link_allowed_roles'] ?? ['owner', 'finance'])),
            'allow_over_receipt' => (bool) ($defaults['allow_over_receipt'] ?? false),
            // Tolerance keys are defaults only; tenant settings page can override per company policy.
            'match_amount_tolerance_percent' => max(0, (float) ($defaults['match_amount_tolerance_percent'] ?? 2)),
            'match_quantity_tolerance_percent' => max(0, (float) ($defaults['match_quantity_tolerance_percent'] ?? 0)),
            'match_date_tolerance_days' => max(0, (int) ($defaults['match_date_tolerance_days'] ?? 7)),
            'block_payment_on_mismatch' => (bool) ($defaults['block_payment_on_mismatch'] ?? true),
            'match_override_allowed_roles' => array_values((array) ($defaults['match_override_allowed_roles'] ?? ['owner', 'finance'])),
            // Policy keys remain tenant-configurable so governance hardening does not require code deploys.
            'mandatory_po_enabled' => (bool) ($defaults['mandatory_po_enabled'] ?? false),
            'mandatory_po_min_amount' => max(0, (int) ($defaults['mandatory_po_min_amount'] ?? 0)),
            'mandatory_po_category_codes' => array_values(array_filter(array_map(
                static fn (mixed $code): string => strtolower(trim((string) $code)),
                (array) ($defaults['mandatory_po_category_codes'] ?? [])
            ))),
            'match_override_requires_maker_checker' => (bool) ($defaults['match_override_requires_maker_checker'] ?? true),
            'stale_commitment_alert_age_hours' => max(1, (int) ($defaults['stale_commitment_alert_age_hours'] ?? 72)),
            'stale_commitment_alert_count_threshold' => max(1, (int) ($defaults['stale_commitment_alert_count_threshold'] ?? 3)),
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
