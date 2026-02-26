<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordAssetMaintenance
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Asset $asset, array $input): AssetEvent
    {
        Gate::forUser($user)->authorize('logMaintenance', $asset);

        if ($asset->status === Asset::STATUS_DISPOSED) {
            throw ValidationException::withMessages([
                'status' => 'Disposed assets cannot receive maintenance logs.',
            ]);
        }

        $validated = Validator::make($input, [
            'event_date' => ['required', 'date'],
            'summary' => ['required', 'string', 'max:160'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'details' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $asset->forceFill([
            'last_maintenance_at' => (string) $validated['event_date'],
            'updated_by' => (int) $user->id,
            // Keep assigned assets assigned; otherwise move active assets to maintenance state.
            'status' => $asset->status === Asset::STATUS_ASSIGNED ? Asset::STATUS_ASSIGNED : Asset::STATUS_IN_MAINTENANCE,
        ])->save();

        $event = AssetEvent::query()->create([
            'company_id' => (int) $asset->company_id,
            'asset_id' => (int) $asset->id,
            'event_type' => AssetEvent::TYPE_MAINTENANCE,
            'event_date' => (string) $validated['event_date'],
            'actor_user_id' => (int) $user->id,
            'amount' => array_key_exists('amount', $validated) ? (int) ($validated['amount'] ?? 0) : null,
            'currency' => strtoupper((string) ($validated['currency'] ?? ($asset->currency ?: 'NGN'))),
            'summary' => trim((string) $validated['summary']),
            'details' => $this->nullableString($validated['details'] ?? null),
        ]);

        $this->activityLogger->log(
            action: 'asset.maintenance.logged',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'event_id' => (int) $event->id,
                'event_date' => (string) $validated['event_date'],
                'amount' => $event->amount,
                'currency' => $event->currency,
            ],
            companyId: (int) $asset->company_id,
            userId: (int) $user->id,
        );

        return $event;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

