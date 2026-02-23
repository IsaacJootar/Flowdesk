<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table): void {
            $table->json('notification_channels')->nullable()->after('max_amount');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table): void {
            $table->dropColumn('notification_channels');
        });
    }
};

