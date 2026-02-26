<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetEvent;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AssetCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAsset
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly AssetCodeGenerator $assetCodeGenerator
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): Asset
    {
        Gate::forUser($user)->authorize('create', Asset::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating assets.',
            ]);
        }

        $validated = Validator::make($input, $this->rules((int) $user->company_id))->validate();
        $assignedAt = $this->nullableDate($validated['assigned_at'] ?? null);
        $assigneeId = $validated['assigned_to_user_id'] ?? null;
        $departmentId = $validated['assigned_department_id'] ?? null;
        $categoryId = $validated['asset_category_id'] ?? null;
        $purchaseAmount = $validated['purchase_amount'] ?? null;

        $asset = DB::transaction(function () use ($user, $validated, $assignedAt, $assigneeId, $departmentId, $categoryId, $purchaseAmount): Asset {
            $attributes = [
                'company_id' => (int) $user->company_id,
                'asset_category_id' => $categoryId ? (int) $categoryId : null,
                'asset_code' => $this->assetCodeGenerator->generateForCompany((int) $user->company_id),
                'name' => trim((string) $validated['name']),
                'serial_number' => $this->nullableString($validated['serial_number'] ?? null),
                'acquisition_date' => $this->nullableDate($validated['acquisition_date'] ?? null),
                'purchase_amount' => $purchaseAmount !== null ? (int) $purchaseAmount : null,
                'currency' => strtoupper((string) ($validated['currency'] ?? ($user->company?->currency_code ?: 'NGN'))),
                'status' => $assigneeId ? Asset::STATUS_ASSIGNED : Asset::STATUS_ACTIVE,
                'condition' => (string) ($validated['condition'] ?? 'good'),
                'notes' => $this->nullableString($validated['notes'] ?? null),
                'assigned_to_user_id' => $assigneeId ? (int) $assigneeId : null,
                'assigned_department_id' => $departmentId ? (int) $departmentId : null,
                'assigned_at' => $assigneeId ? ($assignedAt ?: now()->toDateTimeString()) : null,
                'created_by' => (int) $user->id,
                'updated_by' => (int) $user->id,
            ];

            // Keep create path compatible when reminder columns are not yet migrated.
            if (Schema::hasColumns('assets', ['maintenance_due_date', 'warranty_expires_at'])) {
                $attributes['maintenance_due_date'] = $this->nullableDate($validated['maintenance_due_date'] ?? null);
                $attributes['warranty_expires_at'] = $this->nullableDate($validated['warranty_expires_at'] ?? null);
            }

            $asset = Asset::query()->create($attributes);

            AssetEvent::query()->create([
                'company_id' => (int) $asset->company_id,
                'asset_id' => (int) $asset->id,
                'event_type' => AssetEvent::TYPE_CREATED,
                'event_date' => now()->toDateTimeString(),
                'actor_user_id' => (int) $user->id,
                'summary' => 'Asset registered',
                'details' => 'Asset was added to the register.',
            ]);

            if ($assigneeId) {
                AssetEvent::query()->create([
                    'company_id' => (int) $asset->company_id,
                    'asset_id' => (int) $asset->id,
                    'event_type' => AssetEvent::TYPE_ASSIGNED,
                    'event_date' => $asset->assigned_at ?: now()->toDateTimeString(),
                    'actor_user_id' => (int) $user->id,
                    'target_user_id' => (int) $assigneeId,
                    'target_department_id' => $departmentId ? (int) $departmentId : null,
                    'summary' => 'Initial assignment',
                    'details' => 'Asset was assigned during registration.',
                ]);
            }

            return $asset;
        });

        $this->activityLogger->log(
            action: 'asset.created',
            entityType: Asset::class,
            entityId: (int) $asset->id,
            metadata: [
                'asset_code' => (string) $asset->asset_code,
                'name' => (string) $asset->name,
                'status' => (string) $asset->status,
                'category_id' => $asset->asset_category_id,
                'assigned_to_user_id' => $asset->assigned_to_user_id,
            ],
            companyId: (int) $asset->company_id,
            userId: (int) $user->id,
        );

        return $asset;
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
            'assigned_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'assigned_department_id' => [
                'nullable',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'assigned_at' => ['nullable', 'date'],
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
