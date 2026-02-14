<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('staff')->after('password')->index();
            $table->foreignId('department_id')->nullable()->after('role')->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('department_id');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->softDeletes();

            $table->index('company_id');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['department_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['role']);
            $table->dropColumn([
                'company_id',
                'phone',
                'role',
                'department_id',
                'is_active',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
