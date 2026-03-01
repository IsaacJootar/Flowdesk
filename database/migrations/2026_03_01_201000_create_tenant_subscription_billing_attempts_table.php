<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscription_billing_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_subscription_id');
            $table->foreign('tenant_subscription_id', 'tsba_tenant_subscription_fk')
                ->references('id')
                ->on('tenant_subscriptions')
                ->cascadeOnDelete();
            $table->string('provider_key', 80);
            $table->string('billing_cycle_key', 40);
            $table->string('idempotency_key', 120);
            $table->string('attempt_status', 30)->default('queued'); // queued|processing|webhook_pending|settled|failed|skipped|reversed
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('NGN');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('external_invoice_id', 120)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->string('last_provider_event_id', 120)->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('provider_key', 'tsba_provider_idx');
            $table->index('billing_cycle_key', 'tsba_cycle_idx');
            $table->index('attempt_status', 'tsba_attempt_status_idx');
            $table->index('external_invoice_id', 'tsba_external_invoice_idx');
            $table->index('provider_reference', 'tsba_provider_ref_idx');
            $table->index('last_provider_event_id', 'tsba_last_event_idx');
            $table->unique('idempotency_key', 'tsba_idempotency_unique');

            $table->unique(['company_id', 'tenant_subscription_id', 'billing_cycle_key'], 'tsba_company_subscription_cycle_unique');
            $table->index(['company_id', 'attempt_status'], 'tsba_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_billing_attempts');
    }
};