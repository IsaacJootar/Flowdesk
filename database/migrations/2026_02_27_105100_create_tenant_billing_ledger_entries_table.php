<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_billing_ledger_entries')) {
            return;
        }

        Schema::create('tenant_billing_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('entry_type', 30)->index(); // charge|payment|adjustment
            $table->string('direction', 20)->index(); // debit|credit
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('NGN');
            $table->date('effective_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'effective_date'], 'tbl_company_effective_idx');
            $table->index(['company_id', 'entry_type'], 'tbl_company_type_idx');
            $table->index(['company_id', 'source_type', 'source_id'], 'tbl_company_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_ledger_entries');
    }
};
