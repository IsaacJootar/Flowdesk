<?php

namespace App\Domains\Treasury\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReconciliationMatch extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STREAM_EXECUTION_PAYMENT = 'execution_payment';
    public const STREAM_EXPENSE_EVIDENCE = 'expense_evidence';
    public const STREAM_REIMBURSEMENT = 'reimbursement';

    public const STATUS_MATCHED = 'matched';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'company_id',
        'bank_statement_line_id',
        'match_target_type',
        'match_target_id',
        'match_stream',
        'match_status',
        'confidence_score',
        'matched_by',
        'matched_by_user_id',
        'matched_at',
        'unmatched_at',
        'unmatch_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'matched_at' => 'datetime',
            'unmatched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function matchTarget(): MorphTo
    {
        // Morph target keeps one reconciliation table usable across payouts, expenses, and reimbursements.
        return $this->morphTo(__FUNCTION__, 'match_target_type', 'match_target_id');
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
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
