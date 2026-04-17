<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_expense_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('request_id');
            $table->foreignId('request_payout_execution_attempt_id')->nullable();
            $table->foreignId('expense_id')->nullable();
            $table->string('handoff_status')->default('pending');
            $table->string('handoff_mode')->default('finance_review');
            $table->text('resolution_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'request_id'], 'expense_handoff_company_request_unique');
            $table->index(['company_id', 'handoff_status'], 'expense_handoff_company_status_idx');
            $table->index(['company_id', 'request_payout_execution_attempt_id'], 'expense_handoff_company_attempt_idx');

            $table->foreign('company_id', 'req_exp_handoff_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('request_id', 'req_exp_handoff_request_fk')->references('id')->on('requests')->restrictOnDelete();
            $table->foreign('request_payout_execution_attempt_id', 'req_exp_handoff_attempt_fk')->references('id')->on('request_payout_execution_attempts')->nullOnDelete();
            $table->foreign('expense_id', 'req_exp_handoff_expense_fk')->references('id')->on('expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_expense_handoffs');
    }
};
