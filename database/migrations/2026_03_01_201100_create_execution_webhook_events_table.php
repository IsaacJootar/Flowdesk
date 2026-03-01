<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 80)->index();
            $table->string('external_event_id', 120)->nullable()->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('tenant_subscription_billing_attempt_id')->nullable();
            $table->foreign('tenant_subscription_billing_attempt_id', 'ewe_billing_attempt_fk')
                ->references('id')
                ->on('tenant_subscription_billing_attempts')
                ->nullOnDelete();
            $table->string('event_type', 120)->nullable()->index();
            $table->string('verification_status', 30)->default('pending')->index(); // pending|valid|invalid
            $table->string('processing_status', 30)->default('queued')->index(); // queued|processed|ignored|failed
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('received_at');
            $table->string('signature', 255)->nullable();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider_key', 'external_event_id'], 'ewe_provider_event_idx');
            $table->index(['provider_key', 'processing_status'], 'ewe_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_webhook_events');
    }
};