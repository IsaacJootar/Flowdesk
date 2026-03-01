<?php

namespace App\Domains\Company\Models;

use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_key',
        'external_event_id',
        'company_id',
        'tenant_subscription_id',
        'tenant_subscription_billing_attempt_id',
        'request_payout_execution_attempt_id',
        'event_type',
        'verification_status',
        'processing_status',
        'occurred_at',
        'received_at',
        'signature',
        'headers',
        'payload',
        'normalized_payload',
        'failure_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'headers' => 'array',
            'payload' => 'array',
            'normalized_payload' => 'array',
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

    public function billingAttempt(): BelongsTo
    {
        return $this->belongsTo(TenantSubscriptionBillingAttempt::class, 'tenant_subscription_billing_attempt_id');
    }

    public function payoutAttempt(): BelongsTo
    {
        return $this->belongsTo(RequestPayoutExecutionAttempt::class, 'request_payout_execution_attempt_id');
    }
}