<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('request_code');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approved_amount')->nullable();
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedInteger('current_approval_step')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('requested_by');
            $table->index('department_id');
            $table->index('vendor_id');
            $table->index('status');
            $table->index('workflow_id');
            $table->index('current_approval_step');
            $table->unique(['company_id', 'request_code']);
        });

        Schema::create('request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('step_key')->nullable();
            $table->string('status')->default('pending');
            $table->string('action')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('request_id');
            $table->index('status');
            $table->index('step_order');
            $table->index('acted_by');
            $table->unique(['request_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
        Schema::dropIfExists('requests');
    }
};

