<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vendors', 'bank_code')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('bank_code', 40)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vendors', 'bank_code')) {
            return;
        }

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('bank_code');
        });
    }
};
