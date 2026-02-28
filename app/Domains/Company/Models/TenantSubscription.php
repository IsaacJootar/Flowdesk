<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_code',
        'subscription_status',
        'starts_at',
        'ends_at',
        'grace_until',
        'seat_limit',
        'billing_reference',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'grace_until' => 'date',
            'seat_limit' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function planHistory(): HasMany
    {
        return $this->hasMany(TenantPlanChangeHistory::class, 'tenant_subscription_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TenantBillingLedgerEntry::class, 'tenant_subscription_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TenantBillingAllocation::class, 'tenant_subscription_id');
    }
}
