<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_communication_logs', function (Blueprint $table): void {
            $table->foreignId('recipient_user_id')
                ->nullable()
                ->after('vendor_invoice_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('read_at')->nullable()->after('sent_at');

            $table->index('recipient_user_id');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_communication_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipient_user_id');
            $table->dropColumn('read_at');
        });
    }
};
