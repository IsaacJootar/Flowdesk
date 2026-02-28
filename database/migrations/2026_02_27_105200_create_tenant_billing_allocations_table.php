<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tenant_manual_payment_id')->nullable()->constrained('tenant_manual_payments')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('NGN');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('allocation_status', 30)->default('unapplied')->index(); // allocated|unapplied|reversed
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'allocation_status'], 'tba_company_status_idx');
            $table->index(['company_id', 'period_start', 'period_end'], 'tba_company_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_allocations');
    }
};
