<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('department_budget_id')->nullable()->constrained('department_budgets')->nullOnDelete();
            $table->string('commitment_status', 30)->default('active'); // active|released|consumed|reversed
            $table->unsignedBigInteger('amount');
            $table->string('currency_code', 3)->default('NGN');
            $table->dateTime('effective_at');
            $table->dateTime('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'commitment_status', 'effective_at'], 'pc_company_status_effective_idx');
            $table->index(['company_id', 'department_budget_id', 'commitment_status'], 'pc_company_budget_status_idx');
        });

        Schema::create('invoice_match_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->nullable()->constrained('vendor_invoices')->nullOnDelete();
            $table->string('match_status', 30)->default('pending'); // pending|matched|mismatch|overridden
            $table->decimal('match_score', 5, 2)->nullable();
            $table->string('mismatch_reason', 120)->nullable();
            $table->dateTime('matched_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'match_status'], 'imr_company_status_idx');
            $table->index(['purchase_order_id', 'match_status'], 'imr_po_status_idx');
            $table->index(['vendor_invoice_id', 'match_status'], 'imr_invoice_status_idx');
        });

        Schema::create('invoice_match_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_match_result_id')->constrained('invoice_match_results')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('vendor_invoice_id')->nullable()->constrained('vendor_invoices')->nullOnDelete();
            $table->string('exception_code', 80);
            $table->string('exception_status', 30)->default('open'); // open|resolved|waived
            $table->string('severity', 20)->default('medium'); // low|medium|high|critical
            $table->text('details')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'exception_status', 'severity'], 'ime_company_status_severity_idx');
            $table->index(['company_id', 'exception_code'], 'ime_company_code_idx');
            $table->index(['purchase_order_id', 'exception_status'], 'ime_po_status_idx');
            $table->index(['vendor_invoice_id', 'exception_status'], 'ime_invoice_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_match_exceptions');
        Schema::dropIfExists('invoice_match_results');
        Schema::dropIfExists('procurement_commitments');
    }
};
