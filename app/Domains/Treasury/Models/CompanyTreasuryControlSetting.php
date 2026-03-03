<?php

namespace App\Domains\Treasury\Models;

use App\Domains\Company\Models\Company;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTreasuryControlSetting extends Model
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
        $defaults = (array) config('treasury.defaults', []);

        return [
            'statement_import_max_rows' => max(100, (int) ($defaults['statement_import_max_rows'] ?? 5000)),
            'auto_match_date_window_days' => max(0, (int) ($defaults['auto_match_date_window_days'] ?? 3)),
            'auto_match_amount_tolerance' => max(0, (int) ($defaults['auto_match_amount_tolerance'] ?? 0)),
            'auto_match_min_confidence' => max(1, min(99, (int) ($defaults['auto_match_min_confidence'] ?? 75))),
            'direct_expense_text_similarity_threshold' => max(0, min(100, (int) ($defaults['direct_expense_text_similarity_threshold'] ?? 55))),
            'exception_alert_age_hours' => max(1, (int) ($defaults['exception_alert_age_hours'] ?? 48)),
            'out_of_pocket_requires_reimbursement_link' => (bool) ($defaults['out_of_pocket_requires_reimbursement_link'] ?? true),
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
