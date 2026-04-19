<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('provider')->default('csv');
            $table->string('category_key');
            $table->string('account_code');
            $table->string('account_name')->nullable();
            $table->string('provider_account_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'provider', 'category_key'], 'coa_company_provider_category_unique');
            $table->index('company_id');
            $table->index(['company_id', 'provider']);
            $table->index('category_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_account_mappings');
    }
};
