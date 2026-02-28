<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plan_change_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('previous_plan_code', 30)->nullable();
            $table->string('new_plan_code', 30);
            $table->string('previous_subscription_status', 30)->nullable();
            $table->string('new_subscription_status', 30)->nullable();
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'changed_at']);
            $table->index(['new_plan_code', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plan_change_histories');
    }
};

