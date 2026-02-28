<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_manual_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('NGN');
            $table->string('payment_method', 40)->default('offline_transfer');
            $table->string('reference')->nullable();
            $table->dateTime('received_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'received_at']);
            $table->index(['currency_code', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_manual_payments');
    }
};

