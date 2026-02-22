<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->unsignedBigInteger('request_id')->nullable()->after('expense_code');
            $table->foreignId('voided_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');

            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['request_id']);
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['request_id', 'voided_by', 'voided_at', 'void_reason']);
        });
    }
};

