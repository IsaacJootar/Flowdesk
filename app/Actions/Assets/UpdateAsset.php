<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateAsset
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, Asset $asset, array $input): Asset
    {
        Gate::forUser($user)->authorize('update', $asset);

        $validated = Validator::make($input, $this->rules((int) $asset->company_id))->validate();
        $changes = [
            'asset_category_id' => $validated['asset_category_id'] ? (int) $validated['asset_category_id'] : null,
            'name' => trim((string) $validated['name']),
            'serial_number' => $this->nullableString($validated['serial_number'] ?? null),
            'acquisition_date' => $this->nullableDate($validated['acquisition_date'] ?? null),
            'purchase_amount' => array_key_exists('purchase_amount', $validated) ? (int) ($validated['purchase_amount'] ?? 0) : null,
            'currency' => strtoupper((string) ($validated['currency'] ?? ($asset->currency ?: 'NGN'))),
            'condition' => (string) ($validated['condition'] ?? $asset->condition ?: 'good'),
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'updated_by' => (int) $user->id,
        ];

        // Keep update path compatible when reminder columns are not yet migrated.
        if (Schema::hasColumns('assets', ['maintenance_due_date', 'warranty_expires_at'])) {
            $changes['maintenance_due_date'] = $this->nullableDate($validated['maintenance_due_date'] ?? null);
            $changes['warranty_expires_at'] = $this->nullableDate($validated['warranty_expires_at'] ?? null);
        }

        $dirty = false;
        foreach ($changes as $field => $value) {
            if ($asset->{$field} !== $value) {
                $dirty = true;
                break;
            }
        }

        if (! $dirty) {
            throw ValidationException::withMessages([
                'no_changes' => 'No changes made. Update at least one field before saving.',
            ]);
        }

        $asset->forceFill($changes)->save();

        AssetEvent::query()->create([
            'company_id' => (int) $asset->company_id,
            'asset_id' => (int) $asset->id,
            'event_type' => AssetEvent::TYPE_UPDATED,
            'event_date' => now()->toDateTimeString(),
            'actor_user_id' => (int) $user->id,
            'summary' => 'Asset profile updated',
            'details' => 'Core asset details were modified.',
        ]);

        $this->activityLogger->log(
            action: 'asset.updated',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'name' => (string) $asset->name,
            ],
            companyId: (int) $asset->company_id,
            userId: (int) $user->id,
        );

        return $asset->refresh();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(int $companyId): array
    {
        return [
            'asset_category_id' => [
                'nullable',
                Rule::exists('asset_categories', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:180'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'acquisition_date' => ['nullable', 'date'],
            'purchase_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'condition' => ['nullable', Rule::in(['excellent', 'good', 'fair', 'poor', 'damaged'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'maintenance_due_date' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
