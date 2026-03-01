<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_subscriptions', 'trial_started_at')) {
                $table->dateTime('trial_started_at')->nullable()->after('grace_until');
            }

            if (! Schema::hasColumn('tenant_subscriptions', 'trial_ends_at')) {
                $table->dateTime('trial_ends_at')->nullable()->after('trial_started_at');
            }
        });

        DB::table('tenant_subscriptions')
            ->select(['id', 'trial_started_at', 'trial_ends_at', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $start = $row->trial_started_at
                        ? Carbon::parse((string) $row->trial_started_at)
                        : Carbon::parse((string) ($row->created_at ?? now()));

                    $end = $row->trial_ends_at
                        ? Carbon::parse((string) $row->trial_ends_at)
                        : $start->copy()->addDays(14);

                    DB::table('tenant_subscriptions')
                        ->where('id', $row->id)
                        ->update([
                            'trial_started_at' => $start,
                            'trial_ends_at' => $end,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('tenant_subscriptions', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }

            if (Schema::hasColumn('tenant_subscriptions', 'trial_started_at')) {
                $table->dropColumn('trial_started_at');
            }
        });
    }
};
