<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('lifecycle_status', 20)
                ->default('active')
                ->after('is_active')
                ->index();
            $table->text('status_reason')->nullable()->after('lifecycle_status');
            $table->timestamp('status_updated_at')->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'lifecycle_status',
                'status_reason',
                'status_updated_at',
            ]);
        });
    }
};

