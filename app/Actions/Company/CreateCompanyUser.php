<?php

namespace App\Actions\Company;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateCompanyUser
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, array $input): User
    {
        $this->ensureOwner($actor);

        $hasGenderColumn = Schema::hasColumn('users', 'gender');
        $hasAvatarPathColumn = Schema::hasColumn('users', 'avatar_path');

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(UserRole::values())],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'reports_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', $actor->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($hasGenderColumn) {
            $rules['gender'] = ['required', Rule::in(['male', 'female', 'other'])];
        } else {
            $rules['gender'] = ['nullable', Rule::in(['male', 'female', 'other'])];
        }

        $validated = Validator::make($input, $rules)->validate();

        $payload = [
            'company_id' => (int) $actor->company_id,
            'department_id' => (int) $validated['department_id'],
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ? trim((string) $validated['phone']) : null,
            'password' => $validated['password'],
            'role' => $validated['role'],
            'reports_to_user_id' => $validated['reports_to_user_id'] ? (int) $validated['reports_to_user_id'] : null,
            'is_active' => true,
            'email_verified_at' => now(),
        ];

        if ($hasGenderColumn) {
            $payload['gender'] = $validated['gender'] ?? 'other';
        }

        $createdUser = User::query()->create($payload);

        $avatarFile = $validated['avatar'] ?? null;
        if ($hasAvatarPathColumn && $avatarFile instanceof UploadedFile) {
            $avatarPath = $avatarFile->store(
                path: 'private/avatars/'.(int) $actor->company_id.'/'.$createdUser->id,
                options: 'local'
            );

            $createdUser->forceFill(['avatar_path' => $avatarPath])->save();
        }

        $this->activityLogger->log(
            action: 'identity.user.created',
            entityType: User::class,
            entityId: $createdUser->id,
            metadata: [
                'role' => $createdUser->role,
                'gender' => $hasGenderColumn ? $createdUser->gender : null,
                'department_id' => $createdUser->department_id,
                'reports_to_user_id' => $createdUser->reports_to_user_id,
                'avatar_uploaded' => $hasAvatarPathColumn && $createdUser->avatar_path !== null,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        return $createdUser;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only owner can manage team assignments.');
        }
    }
}
