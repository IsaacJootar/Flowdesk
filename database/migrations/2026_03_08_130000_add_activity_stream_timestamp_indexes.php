<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->index(['company_id', 'updated_at'], 'act_exp_company_updated_idx');
            $table->index(['company_id', 'created_at'], 'act_exp_company_created_idx');
        });

        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->index(['company_id', 'updated_at'], 'act_vi_company_updated_idx');
            $table->index(['company_id', 'created_at'], 'act_vi_company_created_idx');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->index(['company_id', 'updated_at'], 'act_assets_company_updated_idx');
            $table->index(['company_id', 'created_at'], 'act_assets_company_created_idx');
        });

        Schema::table('department_budgets', function (Blueprint $table): void {
            $table->index(['company_id', 'updated_at'], 'act_budget_company_updated_idx');
            $table->index(['company_id', 'created_at'], 'act_budget_company_created_idx');
        });

        Schema::table('reconciliation_exceptions', function (Blueprint $table): void {
            $table->index(['company_id', 'updated_at'], 'act_rex_company_updated_idx');
            $table->index(['company_id', 'created_at'], 'act_rex_company_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_exceptions', function (Blueprint $table): void {
            $table->dropIndex('act_rex_company_created_idx');
            $table->dropIndex('act_rex_company_updated_idx');
        });

        Schema::table('department_budgets', function (Blueprint $table): void {
            $table->dropIndex('act_budget_company_created_idx');
            $table->dropIndex('act_budget_company_updated_idx');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('act_assets_company_created_idx');
            $table->dropIndex('act_assets_company_updated_idx');
        });

        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->dropIndex('act_vi_company_created_idx');
            $table->dropIndex('act_vi_company_updated_idx');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('act_exp_company_created_idx');
            $table->dropIndex('act_exp_company_updated_idx');
        });
    }
};
