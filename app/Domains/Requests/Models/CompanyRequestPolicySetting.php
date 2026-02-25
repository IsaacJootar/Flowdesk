<?php

namespace App\Domains\Requests\Models;

use App\Domains\Company\Models\Company;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRequestPolicySetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const BUDGET_MODE_OFF = 'off';
    public const BUDGET_MODE_WARN = 'warn';
    public const BUDGET_MODE_BLOCK = 'block';

    /**
     * @var array<int, string>
     */
    public const BUDGET_MODES = [
        self::BUDGET_MODE_OFF,
        self::BUDGET_MODE_WARN,
        self::BUDGET_MODE_BLOCK,
    ];

    protected $fillable = [
        'company_id',
        'budget_guardrail_mode',
        'duplicate_detection_enabled',
        'duplicate_window_days',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'duplicate_detection_enabled' => 'boolean',
            'duplicate_window_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public static function defaultAttributes(): array
    {
        return [
            'budget_guardrail_mode' => self::BUDGET_MODE_WARN,
            'duplicate_detection_enabled' => true,
            'duplicate_window_days' => 30,
            'metadata' => null,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

