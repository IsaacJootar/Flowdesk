<?php

namespace App\Actions\Assets;

use App\Domains\Assets\Models\AssetCategory;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CreateAssetCategory
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): AssetCategory
    {
        Gate::forUser($user)->authorize('create', \App\Domains\Assets\Models\Asset::class);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'User must belong to a company before creating asset categories.',
            ]);
        }

        $validated = Validator::make($input, [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('asset_categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', (int) $user->company_id)->whereNull('deleted_at')),
            ],
            // Category code is system-generated from name; manual entry is blocked.
            'code' => ['prohibited'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $code = $this->generateCategoryCode((string) $validated['name'], (int) $user->company_id);

        $category = AssetCategory::query()->create([
            'company_id' => (int) $user->company_id,
            'name' => trim((string) $validated['name']),
            'code' => $code,
            'description' => $this->nullableString($validated['description'] ?? null),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'created_by' => (int) $user->id,
            'updated_by' => (int) $user->id,
        ]);

        $this->activityLogger->log(
            action: 'asset.category.created',
            entityType: AssetCategory::class,
            entityId: (int) $category->id,
            metadata: [
                'name' => (string) $category->name,
                'code' => (string) ($category->code ?? ''),
            ],
            companyId: (int) $category->company_id,
            userId: (int) $user->id,
        );

        return $category;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function generateCategoryCode(string $name, int $companyId): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'CATEGORY';
        }

        $base = substr($base, 0, 40);
        $candidate = $base;
        $suffix = 2;

        while (AssetCategory::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('code', $candidate)
            ->exists()) {
            $suffixText = '_'.$suffix;
            $candidate = substr($base, 0, 40 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $candidate;
    }
}
