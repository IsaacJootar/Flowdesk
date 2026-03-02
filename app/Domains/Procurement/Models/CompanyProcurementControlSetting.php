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
