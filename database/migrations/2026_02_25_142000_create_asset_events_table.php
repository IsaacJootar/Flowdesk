<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_events')) {
            return;
        }

        Schema::create('asset_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->dateTime('event_date');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('summary', 160)->nullable();
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'asset_id', 'event_date'], 'asset_events_company_asset_date_idx');
            $table->index(['company_id', 'event_type'], 'asset_events_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_events');
    }
};

