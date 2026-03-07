<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_payment_rail_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider_key', 80)->nullable();
            $table->string('connection_status', 30)->default('not_connected')->index(); // not_connected|connected|paused
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // passed|failed
            $table->string('last_test_message', 255)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id', 'company_payment_rail_settings_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_rail_settings');
    }
};
