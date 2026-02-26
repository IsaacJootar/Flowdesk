<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets')) {
            return;
        }

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('asset_code', 40);
            $table->string('name', 180);
            $table->string('serial_number', 120)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->unsignedBigInteger('purchase_amount')->nullable();
            $table->string('currency', 8)->default('NGN');
            $table->string('status', 40)->default('active');
            $table->string('condition', 40)->default('good');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('disposed_at')->nullable();
            $table->text('disposal_reason')->nullable();
            $table->unsignedBigInteger('salvage_amount')->nullable();
            $table->date('last_maintenance_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'asset_code'], 'assets_company_asset_code_unique');
            $table->index(['company_id', 'status'], 'assets_company_status_idx');
            $table->index(['company_id', 'asset_category_id'], 'assets_company_category_idx');
            $table->index(['company_id', 'serial_number'], 'assets_company_serial_idx');
            $table->index(['company_id', 'assigned_to_user_id'], 'assets_company_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

