<?php

namespace App\Domains\Requests\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestPayoutExecutionAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'request_id',
        'tenant_subscription_id',
        'provider_key',
        'execution_channel',
        'idempotency_key',
        'execution_status',
        'amount',
        'currency_code',
        'provider_reference',
        'external_transfer_id',
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
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
            'settled_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'provider_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SpendRequest::class, 'request_id')
            ->withoutGlobalScopes()
            ->withTrashed();
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
        return $this->hasMany(ExecutionWebhookEvent::class, 'request_payout_execution_attempt_id');
    }
}

