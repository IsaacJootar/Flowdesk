<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('assets', 'maintenance_due_date')) {
                $table->date('maintenance_due_date')->nullable()->after('last_maintenance_at');
            }

            if (! Schema::hasColumn('assets', 'warranty_expires_at')) {
                $table->date('warranty_expires_at')->nullable()->after('maintenance_due_date');
            }

            $table->index(['company_id', 'maintenance_due_date'], 'assets_company_maintenance_due_idx');
            $table->index(['company_id', 'warranty_expires_at'], 'assets_company_warranty_due_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('assets_company_warranty_due_idx');
            $table->dropIndex('assets_company_maintenance_due_idx');

            if (Schema::hasColumn('assets', 'warranty_expires_at')) {
                $table->dropColumn('warranty_expires_at');
            }

            if (Schema::hasColumn('assets', 'maintenance_due_date')) {
                $table->dropColumn('maintenance_due_date');
            }
        });
    }
};

