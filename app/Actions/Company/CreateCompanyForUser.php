<?php

namespace App\Actions\Company;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCompanyForUser
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(User $user, array $data): Company
    {
        if ($user->company_id !== null) {
            throw ValidationException::withMessages([
                'company' => 'User already belongs to a company.',
            ]);
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('companies', 'slug')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($user, $validated): Company {
            $slug = $this->generateUniqueSlug($validated['slug'] ?? $validated['name']);

            $company = Company::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'currency_code' => strtoupper($validated['currency_code'] ?? 'NGN'),
                'timezone' => $validated['timezone'] ?? 'Africa/Lagos',
                'address' => $validated['address'] ?? null,
                'is_active' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $department = Department::create([
                'company_id' => $company->id,
                'name' => 'General',
                'code' => 'GENERAL',
                'manager_user_id' => null,
                'is_active' => true,
            ]);

            $user->forceFill([
                'company_id' => $company->id,
                'department_id' => $department->id,
                'role' => UserRole::Owner->value,
                'is_active' => true,
            ])->save();

            $this->activityLogger->log(
                action: 'company.created',
                entityType: Company::class,
                entityId: $company->id,
                metadata: ['name' => $company->name],
                companyId: $company->id,
                userId: $user->id,
            );

            $this->activityLogger->log(
                action: 'department.created',
                entityType: Department::class,
                entityId: $department->id,
                metadata: ['name' => $department->name],
                companyId: $company->id,
                userId: $user->id,
            );

            return $company;
        });
    }

    private function generateUniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $counter = 1;

        while (Company::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
