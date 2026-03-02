<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('account_name', 120);
            $table->string('bank_name', 120);
            $table->string('account_number_masked', 40)->nullable();
            $table->string('account_reference', 120)->nullable();
            $table->string('currency_code', 3)->default('NGN');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_statement_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active'], 'ba_company_active_idx');
            $table->index(['company_id', 'is_primary'], 'ba_company_primary_idx');
        });

        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('statement_reference', 120);
            $table->date('statement_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->bigInteger('opening_balance')->nullable();
            $table->bigInteger('closing_balance')->nullable();
            $table->string('import_status', 30)->default('imported'); // imported|failed|partial
            $table->dateTime('imported_at')->nullable();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'bank_account_id', 'statement_reference'], 'bs_company_account_ref_unique');
            $table->index(['company_id', 'statement_date'], 'bs_company_statement_date_idx');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('line_reference', 160)->nullable();
            $table->dateTime('posted_at');
            $table->date('value_date')->nullable();
            $table->text('description')->nullable();
            $table->string('direction', 10)->default('debit'); // debit|credit
            $table->unsignedBigInteger('amount');
            $table->string('currency_code', 3)->default('NGN');
            $table->bigInteger('balance_after')->nullable();
            // Hash keeps imported statement rows idempotent across repeat uploads.
            $table->string('source_hash', 64)->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->dateTime('reconciled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id', 'posted_at'], 'bsl_company_account_posted_idx');
            $table->index(['company_id', 'is_reconciled', 'posted_at'], 'bsl_company_recon_posted_idx');
            $table->unique(['bank_account_id', 'source_hash'], 'bsl_account_hash_unique');
        });

        Schema::create('payment_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('run_code', 80);
            $table->string('run_status', 30)->default('draft'); // draft|processing|completed|failed|canceled
            $table->string('run_type', 30)->default('mixed'); // payout|vendor|reimbursement|mixed
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('currency_code', 3)->default('NGN');
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'run_code'], 'pr_company_code_unique');
            $table->index(['company_id', 'run_status', 'created_at'], 'pr_company_status_created_idx');
        });

        Schema::create('payment_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payment_run_id')->constrained('payment_runs')->cascadeOnDelete();
            $table->foreignId('request_payout_execution_attempt_id')->nullable()->constrained('request_payout_execution_attempts')->nullOnDelete();
            $table->foreignId('vendor_invoice_payment_id')->nullable()->constrained('vendor_invoice_payments')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('item_reference', 120)->nullable();
            $table->string('item_status', 30)->default('queued'); // queued|processing|settled|failed|reversed|skipped
            $table->unsignedBigInteger('amount');
            $table->string('currency_code', 3)->default('NGN');
            $table->string('provider_reference', 120)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'item_status', 'created_at'], 'pri_company_status_created_idx');
            $table->index(['payment_run_id', 'item_status'], 'pri_run_status_idx');
            $table->index(['request_payout_execution_attempt_id'], 'pri_payout_attempt_idx');
            $table->index(['vendor_invoice_payment_id'], 'pri_vendor_payment_idx');
            $table->index(['expense_id'], 'pri_expense_idx');
        });

        Schema::create('reconciliation_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bank_statement_line_id')->constrained('bank_statement_lines')->cascadeOnDelete();
            $table->string('match_target_type', 120);
            $table->unsignedBigInteger('match_target_id');
            $table->string('match_stream', 40)->default('execution_payment'); // execution_payment|expense_evidence|reimbursement
            $table->string('match_status', 30)->default('matched'); // matched|unmatched|conflict|reversed
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('matched_by', 20)->default('system'); // system|manual
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('matched_at')->nullable();
            $table->dateTime('unmatched_at')->nullable();
            $table->text('unmatch_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'match_status', 'matched_at'], 'rm_company_status_matched_idx');
            $table->index(['match_target_type', 'match_target_id'], 'rm_target_idx');
            $table->index(['bank_statement_line_id', 'match_status'], 'rm_line_status_idx');
        });

        Schema::create('reconciliation_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bank_statement_line_id')->nullable()->constrained('bank_statement_lines')->nullOnDelete();
            $table->foreignId('reconciliation_match_id')->nullable()->constrained('reconciliation_matches')->nullOnDelete();
            $table->string('exception_code', 80);
            $table->string('exception_status', 30)->default('open'); // open|resolved|waived
            $table->string('severity', 20)->default('medium'); // low|medium|high|critical
            $table->string('match_stream', 40)->default('execution_payment'); // execution_payment|expense_evidence|reimbursement
            $table->string('next_action', 160)->nullable();
            $table->text('details')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'exception_status', 'severity'], 're_company_status_severity_idx');
            $table->index(['company_id', 'match_stream', 'exception_status'], 're_company_stream_status_idx');
            $table->index(['bank_statement_line_id'], 're_line_idx');
            $table->index(['reconciliation_match_id'], 're_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
        Schema::dropIfExists('reconciliation_matches');
        Schema::dropIfExists('payment_run_items');
        Schema::dropIfExists('payment_runs');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_accounts');
    }
};
