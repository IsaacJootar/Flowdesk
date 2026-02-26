<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_approval_timing_settings')) {
            Schema::create('company_approval_timing_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('step_due_hours')->default(24);
                $table->unsignedInteger('reminder_hours_before_due')->default(6);
                $table->unsignedInteger('escalation_grace_hours')->default(6);
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('company_id', 'uniq_co_appr_timing_company');
            });
        }

        if (! Schema::hasTable('department_approval_timing_overrides')) {
            Schema::create('department_approval_timing_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('step_due_hours');
                $table->unsignedInteger('reminder_hours_before_due');
                $table->unsignedInteger('escalation_grace_hours');
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'department_id'], 'uniq_dept_appr_timing_company_dept');
                $table->index(['company_id'], 'idx_dept_appr_timing_company');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_approval_timing_overrides');
        Schema::dropIfExists('company_approval_timing_settings');
    }
};
