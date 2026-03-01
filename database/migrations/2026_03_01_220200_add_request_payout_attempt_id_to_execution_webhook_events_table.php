<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_webhook_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('execution_webhook_events', 'request_payout_execution_attempt_id')) {
                $table->unsignedBigInteger('request_payout_execution_attempt_id')
                    ->nullable()
                    ->after('tenant_subscription_billing_attempt_id');

                $table->foreign('request_payout_execution_attempt_id', 'ewe_payout_attempt_fk')
                    ->references('id')
                    ->on('request_payout_execution_attempts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('execution_webhook_events', function (Blueprint $table): void {
            if (Schema::hasColumn('execution_webhook_events', 'request_payout_execution_attempt_id')) {
                $table->dropForeign('ewe_payout_attempt_fk');
                $table->dropColumn('request_payout_execution_attempt_id');
            }
        });
    }
};