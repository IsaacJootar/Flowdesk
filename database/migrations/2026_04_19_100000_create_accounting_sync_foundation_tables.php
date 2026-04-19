<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('status', 40)->default('disconnected');
            $table->string('external_tenant_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'provider'], 'acct_integrations_company_provider_unique');
            $table->index(['company_id', 'status'], 'acct_integrations_company_status_idx');
        });

        Schema::create('accounting_provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('provider', 40);
            $table->string('provider_account_id');
            $table->string('account_code')->nullable();
            $table->string('account_name');
            $table->string('account_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider', 'provider_account_id'], 'acct_provider_accounts_unique');
            $table->index(['company_id', 'provider', 'is_active'], 'acct_provider_accounts_lookup_idx');
        });

        Schema::create('accounting_export_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status', 40)->default('completed');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'from_date', 'to_date'], 'acct_export_batches_date_idx');
            $table->index(['company_id', 'status'], 'acct_export_batches_status_idx');
        });

        Schema::create('accounting_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('event_type', 80);
            $table->string('category_key')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->string('currency_code', 3)->default('NGN');
            $table->date('event_date');
            $table->string('description');
            $table->string('debit_account_code')->nullable();
            $table->string('credit_account_code')->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('provider', 40)->default('csv');
            $table->string('provider_record_id')->nullable();
            $table->foreignId('export_batch_id')->nullable()->constrained('accounting_export_batches')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'acct_sync_events_company_status_idx');
            $table->index(['company_id', 'event_date'], 'acct_sync_events_company_date_idx');
            $table->index(['company_id', 'source_type', 'source_id'], 'acct_sync_events_source_idx');
            $table->unique(['company_id', 'source_type', 'source_id', 'event_type', 'provider'], 'acct_sync_events_idempotent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_sync_events');
        Schema::dropIfExists('accounting_export_batches');
        Schema::dropIfExists('accounting_provider_accounts');
        Schema::dropIfExists('accounting_integrations');
    }
};
