<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_request_policy_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('budget_guardrail_mode', 20)->default('warn');
            $table->boolean('duplicate_detection_enabled')->default(true);
            $table->unsignedSmallInteger('duplicate_window_days')->default(30);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
            $table->index(['company_id', 'budget_guardrail_mode'], 'crps_company_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_request_policy_settings');
    }
};
