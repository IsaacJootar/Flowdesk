<?php

namespace App\Services;

use App\Domains\Assets\Models\Asset;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantUsageCounter;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Models\User;

class TenantUsageSnapshotService
{
    public function capture(int $companyId, ?User $actor = null): TenantUsageCounter
    {
        $subscription = TenantSubscription::query()->where('company_id', $companyId)->first();
        $activeUsers = User::query()->where('company_id', $companyId)->where('is_active', true)->count();
        $seatLimit = $subscription?->seat_limit;
        $seatUtilization = $seatLimit && $seatLimit > 0
            ? round(($activeUsers / $seatLimit) * 100, 2)
            : null;

        $warningLevel = 'normal';
        if ($seatUtilization !== null && $seatUtilization >= 100) {
            $warningLevel = 'critical';
        } elseif ($seatUtilization !== null && $seatUtilization >= 85) {
            $warningLevel = 'warning';
        }

        return TenantUsageCounter::query()->create([
            'company_id' => $companyId,
            'snapshot_at' => now(),
            'active_users' => $activeUsers,
            'seat_limit' => $seatLimit,
            'seat_utilization_percent' => $seatUtilization,
            'requests_count' => SpendRequest::query()->where('company_id', $companyId)->count(),
            'expenses_count' => Expense::query()->where('company_id', $companyId)->count(),
            'vendors_count' => Vendor::query()->where('company_id', $companyId)->count(),
            'assets_count' => Asset::query()->where('company_id', $companyId)->count(),
            'warning_level' => $warningLevel,
            'captured_by' => $actor?->id,
        ]);
    }
}

