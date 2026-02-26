<?php

namespace App\Domains\Approvals\Models;

use App\Domains\Company\Models\Company;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyApprovalTimingSetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'step_due_hours',
        'reminder_hours_before_due',
        'escalation_grace_hours',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'step_due_hours' => 'integer',
            'reminder_hours_before_due' => 'integer',
            'escalation_grace_hours' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'step_due_hours' => (int) config('approvals.request_sla.step_due_hours', 24),
            'reminder_hours_before_due' => (int) config('approvals.request_sla.reminder_hours_before_due', 6),
            'escalation_grace_hours' => (int) config('approvals.request_sla.escalation_grace_hours', 6),
            'metadata' => null,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
