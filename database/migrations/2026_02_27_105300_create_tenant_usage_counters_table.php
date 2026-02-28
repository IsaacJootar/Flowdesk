<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamp('snapshot_at');
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('seat_limit')->nullable();
            $table->decimal('seat_utilization_percent', 5, 2)->nullable();
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('expenses_count')->default(0);
            $table->unsignedInteger('vendors_count')->default(0);
            $table->unsignedInteger('assets_count')->default(0);
            $table->string('warning_level', 20)->default('normal'); // normal|warning|critical
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'snapshot_at'], 'tuc_company_snapshot_idx');
            $table->index(['company_id', 'warning_level'], 'tuc_company_warning_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_counters');
    }
};
