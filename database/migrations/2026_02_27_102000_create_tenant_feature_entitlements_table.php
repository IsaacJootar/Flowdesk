<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_feature_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('requests_enabled')->default(true);
            $table->boolean('expenses_enabled')->default(true);
            $table->boolean('vendors_enabled')->default(true);
            $table->boolean('budgets_enabled')->default(true);
            $table->boolean('assets_enabled')->default(true);
            $table->boolean('reports_enabled')->default(true);
            $table->boolean('communications_enabled')->default(true);
            $table->boolean('ai_enabled')->default(false);
            $table->boolean('fintech_enabled')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_entitlements');
    }
};

