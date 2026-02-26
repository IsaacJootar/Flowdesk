<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DisposeAsset
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Asset $asset, array $input): Asset
    {
        Gate::forUser($user)->authorize('dispose', $asset);

        if ($asset->status === Asset::STATUS_DISPOSED) {
            throw ValidationException::withMessages([
                'status' => 'Asset is already disposed.',
            ]);
        }

        $validated = Validator::make($input, [
            'event_date' => ['required', 'date'],
            'summary' => ['required', 'string', 'max:160'],
            'salvage_amount' => ['nullable', 'integer', 'min:0'],
            'details' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $asset->forceFill([
            'status' => Asset::STATUS_DISPOSED,
            'disposed_at' => (string) $validated['event_date'],
            'disposal_reason' => trim((string) $validated['summary']),
            'salvage_amount' => array_key_exists('salvage_amount', $validated) ? (int) ($validated['salvage_amount'] ?? 0) : null,
            'assigned_to_user_id' => null,
            'assigned_department_id' => null,
            'assigned_at' => null,
            'updated_by' => (int) $user->id,
        ])->save();

        AssetEvent::query()->create([
            'company_id' => (int) $asset->company_id,
            'asset_id' => (int) $asset->id,
            'event_type' => AssetEvent::TYPE_DISPOSED,
            'event_date' => (string) $validated['event_date'],
            'actor_user_id' => (int) $user->id,
            'amount' => $asset->salvage_amount,
            'currency' => strtoupper((string) ($asset->currency ?: 'NGN')),
            'summary' => trim((string) $validated['summary']),
            'details' => $this->nullableString($validated['details'] ?? null),
        ]);

        $this->activityLogger->log(
            action: 'asset.disposed',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'disposed_at' => (string) $asset->disposed_at,
                'salvage_amount' => $asset->salvage_amount,
            ],
            companyId: (int) $asset->company_id,
            userId: (int) $user->id,
        );

        return $asset->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

