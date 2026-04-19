<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Company\Models\Company;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSyncEvent extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'event_type',
        'category_key',
        'amount',
        'currency_code',
        'event_date',
        'description',
        'debit_account_code',
        'credit_account_code',
        'status',
        'attempt_count',
        'next_attempt_at',
        'last_error',
        'provider',
        'provider_record_id',
        'export_batch_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'event_date' => 'date',
            'attempt_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function exportBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingExportBatch::class, 'export_batch_id');
    }
}
