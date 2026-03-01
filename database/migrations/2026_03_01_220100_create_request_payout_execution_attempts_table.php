<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_payout_execution_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_subscription_id')->nullable();
            $table->foreign('tenant_subscription_id', 'rpea_tenant_subscription_fk')
                ->references('id')
                ->on('tenant_subscriptions')
                ->nullOnDelete();
            $table->string('provider_key', 80);
            $table->string('execution_channel', 40)->default('bank_transfer');
            $table->string('idempotency_key', 120);
            $table->string('execution_status', 30)->default('queued'); // queued|processing|webhook_pending|settled|failed|reversed|skipped
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('NGN');
            $table->string('provider_reference', 120)->nullable();
            $table->string('external_transfer_id', 120)->nullable();
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

            $table->index('provider_key', 'rpea_provider_idx');
            $table->index('execution_status', 'rpea_execution_status_idx');
            $table->index('provider_reference', 'rpea_provider_ref_idx');
            $table->index('external_transfer_id', 'rpea_external_transfer_idx');
            $table->index('last_provider_event_id', 'rpea_last_event_idx');
            $table->unique('idempotency_key', 'rpea_idempotency_unique');

            $table->unique(['request_id'], 'request_payout_execution_attempts_request_unique');
            $table->index(['company_id', 'execution_status'], 'rpea_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_payout_execution_attempts');
    }
};