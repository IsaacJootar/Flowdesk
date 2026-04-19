<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('requests', 'accounting_category_key')) {
                $table->string('accounting_category_key')->nullable()->after('currency');
                $table->index('accounting_category_key');
            }
        });

        Schema::table('request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('request_items', 'accounting_category_key')) {
                $table->string('accounting_category_key')->nullable()->after('category');
                $table->index('accounting_category_key');
            }
        });

        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'accounting_category_key')) {
                $table->string('accounting_category_key')->nullable()->after('payment_method');
                $table->index('accounting_category_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'accounting_category_key')) {
                $table->dropIndex(['accounting_category_key']);
                $table->dropColumn('accounting_category_key');
            }
        });

        Schema::table('request_items', function (Blueprint $table): void {
            if (Schema::hasColumn('request_items', 'accounting_category_key')) {
                $table->dropIndex(['accounting_category_key']);
                $table->dropColumn('accounting_category_key');
            }
        });

        Schema::table('requests', function (Blueprint $table): void {
            if (Schema::hasColumn('requests', 'accounting_category_key')) {
                $table->dropIndex(['accounting_category_key']);
                $table->dropColumn('accounting_category_key');
            }
        });
    }
};
