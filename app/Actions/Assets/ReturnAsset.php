<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReturnAsset
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Asset $asset, array $input): Asset
    {
        Gate::forUser($user)->authorize('assign', $asset);

        if ($asset->status === Asset::STATUS_DISPOSED) {
            throw ValidationException::withMessages([
                'status' => 'Disposed assets cannot be returned to inventory.',
            ]);
        }

        if (! $asset->assigned_to_user_id) {
            throw ValidationException::withMessages([
                'assignment' => 'Only assigned assets can be returned.',
            ]);
        }

        $validated = Validator::make($input, [
            'event_date' => ['required', 'date'],
            'summary' => ['nullable', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $previousAssignment = [
            'assigned_to_user_id' => $asset->assigned_to_user_id,
            'assigned_department_id' => $asset->assigned_department_id,
            'assigned_at' => optional($asset->assigned_at)->toDateTimeString(),
        ];

        $asset->forceFill([
            'assigned_to_user_id' => null,
            'assigned_department_id' => null,
            'assigned_at' => null,
            'status' => Asset::STATUS_ACTIVE,
            'updated_by' => (int) $user->id,
        ])->save();

        AssetEvent::query()->create([
            'company_id' => (int) $asset->company_id,
            'asset_id' => (int) $asset->id,
            'event_type' => AssetEvent::TYPE_RETURNED,
            'event_date' => (string) $validated['event_date'],
            'actor_user_id' => (int) $user->id,
            'target_user_id' => null,
            'target_department_id' => null,
            'summary' => $this->nullableString($validated['summary'] ?? null) ?: 'Asset returned to inventory',
            'details' => $this->nullableString($validated['details'] ?? null),
            'metadata' => [
                'previous_assignment' => $previousAssignment,
            ],
        ]);

        $this->activityLogger->log(
            action: 'asset.returned',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'previous_assignment' => $previousAssignment,
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

