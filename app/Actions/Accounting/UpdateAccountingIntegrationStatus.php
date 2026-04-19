<?php

namespace App\Actions\Accounting;

use App\Domains\Accounting\Models\AccountingIntegration;
use App\Enums\AccountingProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateAccountingIntegrationStatus
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, string $provider, string $status): AccountingIntegration
    {
        if (! in_array((string) $actor->role, [UserRole::Owner->value, UserRole::Finance->value], true)) {
            throw new AuthorizationException('Only owner and finance can update accounting integrations.');
        }

        if (! $actor->company_id) {
            throw ValidationException::withMessages([
                'company' => 'You must belong to an organization before updating accounting integrations.',
            ]);
        }

        $validated = Validator::make([
            'provider' => strtolower(trim($provider)),
            'status' => strtolower(trim($status)),
        ], [
            'provider' => ['required', Rule::in([
                AccountingProvider::QuickBooks->value,
                AccountingProvider::Sage->value,
                AccountingProvider::Xero->value,
            ])],
            'status' => ['required', Rule::in(['disconnected', 'disabled'])],
        ])->validate();

        $integration = AccountingIntegration::query()->withoutGlobalScopes()->firstOrNew([
            'company_id' => (int) $actor->company_id,
            'provider' => (string) $validated['provider'],
        ]);

        if (! $integration->exists) {
            $integration->created_by = (int) $actor->id;
        }

        $integration->forceFill([
            'status' => (string) $validated['status'],
            'updated_by' => (int) $actor->id,
            'metadata' => array_merge((array) ($integration->metadata ?? []), [
                'manual_status_updated_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        $this->activityLogger->log(
            action: 'accounting.integration.status_updated',
            entityType: AccountingIntegration::class,
            entityId: (int) $integration->id,
            metadata: [
                'provider' => (string) $integration->provider,
                'status' => (string) $integration->status,
            ],
            companyId: (int) $actor->company_id,
            userId: (int) $actor->id,
        );

        return $integration;
    }
}
