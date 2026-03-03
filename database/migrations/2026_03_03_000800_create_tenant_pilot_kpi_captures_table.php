<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_pilot_kpi_captures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('window_label', 30)->default('pilot'); // baseline|pilot|custom
            $table->dateTime('window_start');
            $table->dateTime('window_end');
            $table->decimal('match_pass_rate_percent', 8, 2)->default(0);
            $table->unsignedInteger('open_procurement_exceptions')->default(0);
            $table->decimal('procurement_exception_avg_open_hours', 10, 2)->default(0);
            $table->decimal('auto_reconciliation_rate_percent', 8, 2)->default(0);
            $table->unsignedInteger('open_treasury_exceptions')->default(0);
            $table->decimal('treasury_exception_avg_open_hours', 10, 2)->default(0);
            $table->unsignedInteger('blocked_payout_count')->default(0);
            $table->unsignedInteger('manual_override_count')->default(0);
            $table->unsignedInteger('incident_count')->default(0);
            $table->decimal('incident_rate_per_week', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'window_label', 'captured_at'], 'tpk_company_label_captured_idx');
            $table->index(['company_id', 'window_start', 'window_end'], 'tpk_company_window_idx');
            $table->unique(
                ['company_id', 'window_label', 'window_start', 'window_end'],
                'tpk_company_label_window_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pilot_kpi_captures');
    }
};
