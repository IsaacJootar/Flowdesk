<?php

namespace App\Domains\Treasury\Models;

use App\Models\User;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a bank account linked via Mono Connect Open Banking.
 *
 * One record per linked bank account.  The `mono_account_id` is the key
 * used by MonoConnectService and ImportMonoStatementService to pull
 * live transactions and balance data from the Mono API.
 */
class MonoConnectAccount extends Model
{
    use CompanyScoped;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'mono_account_id',
        'institution_name',
        'account_name',
        'account_number_last4',
        'currency_code',
        'balance_amount',
        'balance_synced_at',
        'is_active',
        'last_synced_at',
        'sync_error',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance_amount'    => 'integer',
            'balance_synced_at' => 'datetime',
            'is_active'         => 'boolean',
            'last_synced_at'    => 'datetime',
            'metadata'          => 'array',
        ];
    }

    /**
     * The BankAccount this Mono connection is linked to.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Return the balance in major units (e.g. NGN) for display purposes.
     * The raw stored value is in kobo.
     */
    public function getBalanceMajorAttribute(): float
    {
        return round(($this->balance_amount ?? 0) / 100, 2);
    }
}
