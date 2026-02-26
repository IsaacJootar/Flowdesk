<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_communication_logs')) {
            return;
        }

        Schema::create('asset_communication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('channel');
            $table->string('status')->default('queued');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->date('reminder_date');
            $table->string('dedupe_key', 190)->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('asset_id');
            $table->index('recipient_user_id');
            $table->index('event');
            $table->index('channel');
            $table->index('status');
            $table->index('reminder_date');
            $table->index('read_at');
            $table->index('dedupe_key');
            $table->index(['company_id', 'status', 'created_at'], 'asset_comm_logs_company_status_created_idx');
            $table->index(['company_id', 'recipient_user_id', 'read_at'], 'asset_comm_logs_company_recipient_read_idx');
            $table->unique(['company_id', 'dedupe_key'], 'asset_comm_logs_unique_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_communication_logs');
    }
};

