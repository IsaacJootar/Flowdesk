<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->string('payment_execution_mode', 30)->default('decision_only')->after('subscription_status')->index();
            $table->string('execution_provider', 80)->nullable()->after('seat_limit');
            $table->timestamp('execution_enabled_at')->nullable()->after('execution_provider');
            $table->foreignId('execution_enabled_by')->nullable()->after('execution_enabled_at')->constrained('users')->nullOnDelete();
            $table->decimal('execution_max_transaction_amount', 15, 2)->nullable()->after('execution_enabled_by');
            $table->decimal('execution_daily_cap_amount', 15, 2)->nullable()->after('execution_max_transaction_amount');
            $table->decimal('execution_monthly_cap_amount', 15, 2)->nullable()->after('execution_daily_cap_amount');
            $table->decimal('execution_maker_checker_threshold_amount', 15, 2)->nullable()->after('execution_monthly_cap_amount');
            $table->json('execution_allowed_channels')->nullable()->after('execution_maker_checker_threshold_amount');
            $table->text('execution_policy_notes')->nullable()->after('execution_allowed_channels');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('execution_enabled_by');
            $table->dropColumn([
                'payment_execution_mode',
                'execution_provider',
                'execution_enabled_at',
                'execution_max_transaction_amount',
                'execution_daily_cap_amount',
                'execution_monthly_cap_amount',
                'execution_maker_checker_threshold_amount',
                'execution_allowed_channels',
                'execution_policy_notes',
            ]);
        });
    }
};
