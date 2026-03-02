<?php

namespace App\Domains\Treasury\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationException extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_WAIVED = 'waived';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const STREAM_EXECUTION_PAYMENT = 'execution_payment';
    public const STREAM_EXPENSE_EVIDENCE = 'expense_evidence';
    public const STREAM_REIMBURSEMENT = 'reimbursement';

    protected $fillable = [
        'company_id',
        'bank_statement_line_id',
        'reconciliation_match_id',
        'exception_code',
        'exception_status',
        'severity',
        'match_stream',
        'next_action',
        'details',
        'resolution_notes',
        'resolved_at',
        'resolved_by_user_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ReconciliationMatch::class, 'reconciliation_match_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
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
