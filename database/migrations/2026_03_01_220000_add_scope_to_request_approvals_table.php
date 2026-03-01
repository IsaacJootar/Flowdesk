<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_approvals', 'scope')) {
                $table->string('scope', 40)->default('request')->after('request_id')->index();
            }
        });

        DB::table('request_approvals')
            ->whereNull('scope')
            ->update(['scope' => 'request']);

        Schema::table('request_approvals', function (Blueprint $table): void {
            try {
                $table->dropUnique('request_approvals_request_id_step_order_unique');
            } catch (Throwable) {
                // Unique may already be replaced in some environments.
            }

            try {
                $table->unique(['request_id', 'scope', 'step_order'], 'request_approvals_request_scope_step_unique');
            } catch (Throwable) {
                // Index already exists.
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_approvals', function (Blueprint $table): void {
            try {
                $table->dropUnique('request_approvals_request_scope_step_unique');
            } catch (Throwable) {
                // ignore
            }

            try {
                $table->unique(['request_id', 'step_order'], 'request_approvals_request_id_step_order_unique');
            } catch (Throwable) {
                // ignore
            }

            if (Schema::hasColumn('request_approvals', 'scope')) {
                try {
                    $table->dropIndex(['scope']);
                } catch (Throwable) {
                    // ignore
                }
                $table->dropColumn('scope');
            }
        });
    }
};