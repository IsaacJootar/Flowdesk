<?php

namespace App\Domains\Treasury\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementLine extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'company_id',
        'bank_statement_id',
        'bank_account_id',
        'line_reference',
        'posted_at',
        'value_date',
        'description',
        'direction',
        'amount',
        'currency_code',
        'balance_after',
        'source_hash',
        'is_reconciled',
        'reconciled_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'value_date' => 'date',
            'amount' => 'integer',
            'balance_after' => 'integer',
            'is_reconciled' => 'boolean',
            'reconciled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class, 'bank_statement_line_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ReconciliationException::class, 'bank_statement_line_id');
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
