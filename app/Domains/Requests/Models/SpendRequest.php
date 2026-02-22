<?php

namespace App\Domains\Requests\Models;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpendRequest extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'requests';

    protected $fillable = [
        'company_id',
        'request_code',
        'requested_by',
        'department_id',
        'vendor_id',
        'workflow_id',
        'title',
        'description',
        'amount',
        'currency',
        'status',
        'approved_amount',
        'paid_amount',
        'current_approval_step',
        'submitted_at',
        'decided_at',
        'decision_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'approved_amount' => 'integer',
            'paid_amount' => 'integer',
            'current_approval_step' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }
}
