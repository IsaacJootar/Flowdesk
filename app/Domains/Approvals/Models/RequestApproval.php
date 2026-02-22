<?php

namespace App\Domains\Approvals\Models;

use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestApproval extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $table = 'request_approvals';

    protected $fillable = [
        'company_id',
        'request_id',
        'workflow_step_id',
        'step_order',
        'step_key',
        'status',
        'action',
        'acted_by',
        'acted_at',
        'comment',
        'from_status',
        'to_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'acted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}

