<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_approvals', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('acted_at');
            }

            if (! Schema::hasColumn('request_approvals', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('due_at');
            }

            if (! Schema::hasColumn('request_approvals', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('reminder_sent_at');
            }

            if (! Schema::hasColumn('request_approvals', 'reminder_count')) {
                $table->unsignedSmallInteger('reminder_count')->default(0)->after('escalated_at');
            }
        });

        Schema::table('request_communication_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_communication_logs', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_communication_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('request_communication_logs', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });

        Schema::table('request_approvals', function (Blueprint $table): void {
            $columns = [];
            foreach (['due_at', 'reminder_sent_at', 'escalated_at', 'reminder_count'] as $column) {
                if (Schema::hasColumn('request_approvals', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
