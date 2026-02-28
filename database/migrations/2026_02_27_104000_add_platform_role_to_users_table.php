<?php

use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role')->nullable()->after('role')->index();
        });

        // Backfill existing global owner accounts as platform owners.
        DB::table('users')
            ->whereNull('company_id')
            ->where('role', UserRole::Owner->value)
            ->whereNull('platform_role')
            ->update(['platform_role' => PlatformUserRole::PlatformOwner->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['platform_role']);
            $table->dropColumn('platform_role');
        });
    }
};

