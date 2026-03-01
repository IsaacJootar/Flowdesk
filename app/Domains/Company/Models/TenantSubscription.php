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
        'payment_execution_mode',
        'starts_at',
        'ends_at',
        'grace_until',
        'trial_started_at',
        'trial_ends_at',
        'seat_limit',
        'execution_provider',
        'execution_enabled_at',
        'execution_enabled_by',
        'execution_max_transaction_amount',
        'execution_daily_cap_amount',
        'execution_monthly_cap_amount',
        'execution_maker_checker_threshold_amount',
        'execution_allowed_channels',
        'execution_policy_notes',
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
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'seat_limit' => 'integer',
            'execution_enabled_at' => 'datetime',
            'execution_max_transaction_amount' => 'decimal:2',
            'execution_daily_cap_amount' => 'decimal:2',
            'execution_monthly_cap_amount' => 'decimal:2',
            'execution_maker_checker_threshold_amount' => 'decimal:2',
            'execution_allowed_channels' => 'array',
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

    public function executionEnabler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_enabled_by');
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
