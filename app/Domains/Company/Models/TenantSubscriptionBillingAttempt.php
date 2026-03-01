<?php

namespace App\Domains\Company\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscriptionBillingAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tenant_subscription_id',
        'provider_key',
        'billing_cycle_key',
        'idempotency_key',
        'attempt_status',
        'amount',
        'currency_code',
        'period_start',
        'period_end',
        'external_invoice_id',
        'provider_reference',
        'last_provider_event_id',
        'attempt_count',
        'queued_at',
        'processed_at',
        'settled_at',
        'failed_at',
        'next_retry_at',
        'error_code',
        'error_message',
        'provider_response',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'attempt_count' => 'integer',
            'provider_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ExecutionWebhookEvent::class, 'tenant_subscription_billing_attempt_id');
    }
}