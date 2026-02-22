<?php

namespace App\Domains\Approvals\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflowStep extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'approval_workflow_steps';

    protected $fillable = [
        'company_id',
        'workflow_id',
        'step_order',
        'step_key',
        'actor_type',
        'actor_value',
        'min_amount',
        'max_amount',
        'requires_all',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'requires_all' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function requestApprovals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'workflow_step_id');
    }
}

