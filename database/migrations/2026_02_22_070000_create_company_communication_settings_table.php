<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('email_configured')->default(false);
            $table->boolean('sms_configured')->default(false);
            $table->json('fallback_order')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
            $table->index(['company_id', 'in_app_enabled']);
            $table->index(['company_id', 'email_enabled']);
            $table->index(['company_id', 'sms_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_communication_settings');
    }
};

