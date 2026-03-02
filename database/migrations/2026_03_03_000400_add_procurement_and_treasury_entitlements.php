<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_feature_entitlements', function (Blueprint $table): void {
            $table->boolean('procurement_enabled')->default(false)->after('fintech_enabled');
            $table->boolean('treasury_enabled')->default(false)->after('procurement_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_feature_entitlements', function (Blueprint $table): void {
            $table->dropColumn(['procurement_enabled', 'treasury_enabled']);
        });
    }
};
