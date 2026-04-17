<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestExpenseHandoff extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPENSE_CREATED = 'expense_created';
    public const STATUS_NOT_REQUIRED = 'not_required';

    public const MODE_MANUAL = 'manual';
    public const MODE_FINANCE_REVIEW = 'finance_review';
    public const MODE_AUTO_CREATE = 'auto_create';

    protected $fillable = [
        'company_id',
        'request_id',
        'request_payout_execution_attempt_id',
        'expense_id',
        'handoff_status',
        'handoff_mode',
        'resolution_reason',
        'metadata',
        'created_by',
        'updated_by',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id')->withTrashed();
    }

    public function payoutAttempt(): BelongsTo
    {
        return $this->belongsTo(RequestPayoutExecutionAttempt::class, 'request_payout_execution_attempt_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
