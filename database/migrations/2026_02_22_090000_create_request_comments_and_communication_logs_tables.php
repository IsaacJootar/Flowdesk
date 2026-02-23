<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('company_id');
            $table->index('request_id');
            $table->index('user_id');
        });

        Schema::create('request_communication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('request_approval_id')->nullable()->constrained('request_approvals')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('channel');
            $table->string('status')->default('queued');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('request_id');
            $table->index('event');
            $table->index('channel');
            $table->index('status');
            $table->index('recipient_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_communication_logs');
        Schema::dropIfExists('request_comments');
    }
};

