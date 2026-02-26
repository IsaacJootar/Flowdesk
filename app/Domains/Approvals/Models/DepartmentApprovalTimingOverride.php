<?php

namespace App\Domains\Approvals\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentApprovalTimingOverride extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'department_id',
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
            'department_id' => 'integer',
            'step_due_hours' => 'integer',
            'reminder_hours_before_due' => 'integer',
            'escalation_grace_hours' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
