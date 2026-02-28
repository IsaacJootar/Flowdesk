<?php

namespace App\Services;

use App\Domains\Company\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TenantSeatGovernanceService
{
    /**
     * @return array{seat_limit:int|null,active_users:int,remaining:int|null,utilization_percent:float|null}
     */
    public function summary(int $companyId): array
    {
        $seatLimit = TenantSubscription::query()
            ->where('company_id', $companyId)
            ->value('seat_limit');

        $seatLimit = $seatLimit !== null ? (int) $seatLimit : null;
        $activeUsers = User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        if ($seatLimit === null || $seatLimit <= 0) {
            return [
                'seat_limit' => null,
                'active_users' => $activeUsers,
                'remaining' => null,
                'utilization_percent' => null,
            ];
        }

        $remaining = max(0, $seatLimit - $activeUsers);
        $utilization = round(($activeUsers / $seatLimit) * 100, 2);

        return [
            'seat_limit' => $seatLimit,
            'active_users' => $activeUsers,
            'remaining' => $remaining,
            'utilization_percent' => $utilization,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanAddActiveUser(int $companyId, int $additionalUsers = 1): void
    {
        $summary = $this->summary($companyId);
        $seatLimit = $summary['seat_limit'];

        if ($seatLimit === null) {
            return;
        }

        $projected = (int) $summary['active_users'] + max(1, $additionalUsers);
        if ($projected <= $seatLimit) {
            return;
        }

        throw ValidationException::withMessages([
            'seat_limit' => sprintf(
                'Seat limit reached (%d/%d active). Increase tenant seat limit in Platform Tenant Management or deactivate another user first.',
                (int) $summary['active_users'],
                $seatLimit
            ),
        ]);
    }
}

