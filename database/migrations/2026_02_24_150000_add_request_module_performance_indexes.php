<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'updated_at'], 'req_company_status_updated_idx');
            $table->index(['company_id', 'requested_by', 'updated_at'], 'req_company_requester_updated_idx');
            $table->index(['company_id', 'department_id', 'updated_at'], 'req_company_department_updated_idx');
            $table->index(['company_id', 'submitted_at'], 'req_company_submitted_idx');
            $table->index(['company_id', 'decided_at'], 'req_company_decided_idx');
            $table->index(['company_id', 'created_at'], 'req_company_created_idx');
        });

        Schema::table('request_approvals', function (Blueprint $table): void {
            $table->index(['company_id', 'acted_by', 'action'], 'reqapp_company_actor_action_idx');
            $table->index(['company_id', 'status', 'due_at'], 'reqapp_company_status_due_idx');
        });

        Schema::table('request_communication_logs', function (Blueprint $table): void {
            $table->index(['company_id', 'status', 'created_at'], 'reqlog_company_status_created_idx');
            $table->index(['company_id', 'recipient_user_id', 'read_at'], 'reqlog_company_recipient_read_idx');
        });
    }

    public function down(): void
    {
        Schema::table('request_communication_logs', function (Blueprint $table): void {
            $table->dropIndex('reqlog_company_recipient_read_idx');
            $table->dropIndex('reqlog_company_status_created_idx');
        });

        Schema::table('request_approvals', function (Blueprint $table): void {
            $table->dropIndex('reqapp_company_status_due_idx');
            $table->dropIndex('reqapp_company_actor_action_idx');
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex('req_company_created_idx');
            $table->dropIndex('req_company_decided_idx');
            $table->dropIndex('req_company_submitted_idx');
            $table->dropIndex('req_company_department_updated_idx');
            $table->dropIndex('req_company_requester_updated_idx');
            $table->dropIndex('req_company_status_updated_idx');
        });
    }
};
