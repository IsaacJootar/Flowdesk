<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBillingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tenant_manual_payment_id',
        'tenant_subscription_id',
        'amount',
        'currency_code',
        'period_start',
        'period_end',
        'allocation_status',
        'note',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manualPayment(): BelongsTo
    {
        return $this->belongsTo(TenantManualPayment::class, 'tenant_manual_payment_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

