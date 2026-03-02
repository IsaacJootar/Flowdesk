<?php

namespace App\Domains\Treasury\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_reference',
        'statement_date',
        'period_start',
        'period_end',
        'opening_balance',
        'closing_balance',
        'import_status',
        'imported_at',
        'imported_by_user_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_balance' => 'integer',
            'closing_balance' => 'integer',
            'imported_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_id');
    }
}
