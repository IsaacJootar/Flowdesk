<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_pilot_wave_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('wave_label', 40)->default('wave-1');
            $table->string('outcome', 20)->default('go'); // go|hold|no_go
            $table->timestamp('decision_at');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'decision_at'], 'tpwo_company_decision_idx');
            $table->index(['outcome', 'decision_at'], 'tpwo_outcome_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pilot_wave_outcomes');
    }
};
