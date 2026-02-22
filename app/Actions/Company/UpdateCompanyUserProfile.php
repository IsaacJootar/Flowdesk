<?php

namespace App\Actions\Company;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateCompanyUserProfile
{
    public function __construct(private readonly ActivityLogger $activityLogger)
    {
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, User $subject, array $input): User
    {
        $this->ensureOwner($actor);

        if ((int) $actor->company_id !== (int) $subject->company_id) {
            throw new AuthorizationException('Cross-company user update is not allowed.');
        }

        $hasGenderColumn = Schema::hasColumn('users', 'gender');
        $hasAvatarPathColumn = Schema::hasColumn('users', 'avatar_path');

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($subject->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];

        if ($hasGenderColumn) {
            $rules['gender'] = ['required', Rule::in(['male', 'female', 'other'])];
        } else {
            $rules['gender'] = ['nullable', Rule::in(['male', 'female', 'other'])];
        }

        $validated = Validator::make($input, $rules)->validate();

        $removeAvatar = $hasAvatarPathColumn && (bool) ($validated['remove_avatar'] ?? false);
        $avatarFile = $validated['avatar'] ?? null;
        $oldAvatarPath = $hasAvatarPathColumn ? $subject->avatar_path : null;
        $newAvatarPath = $oldAvatarPath;

        if ($hasAvatarPathColumn && $avatarFile instanceof UploadedFile) {
            $newAvatarPath = $avatarFile->store(
                path: 'private/avatars/'.(int) $actor->company_id.'/'.$subject->id,
                options: 'local'
            );
        } elseif ($hasAvatarPathColumn && $removeAvatar) {
            $newAvatarPath = null;
        }

        $updates = [
            'name' => trim((string) $validated['name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone' => $validated['phone'] ? trim((string) $validated['phone']) : null,
        ];

        if ($hasGenderColumn) {
            $updates['gender'] = $validated['gender'] ?? $subject->gender;
        }

        if ($hasAvatarPathColumn) {
            $updates['avatar_path'] = $newAvatarPath;
        }

        $subject->forceFill($updates)->save();

        if ($oldAvatarPath && $oldAvatarPath !== $newAvatarPath) {
            Storage::disk('local')->delete($oldAvatarPath);
        }

        $this->activityLogger->log(
            action: 'identity.user.profile.updated',
            entityType: User::class,
            entityId: $subject->id,
            metadata: [
                'name' => $subject->name,
                'email' => $subject->email,
                'gender' => $hasGenderColumn ? $subject->gender : null,
                'avatar_updated' => $hasAvatarPathColumn && ($avatarFile instanceof UploadedFile || $removeAvatar),
                'avatar_removed' => $hasAvatarPathColumn && $removeAvatar,
            ],
            companyId: (int) $actor->company_id,
            userId: $actor->id,
        );

        return $subject;
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureOwner(User $actor): void
    {
        if (! $actor->hasRole(UserRole::Owner)) {
            throw new AuthorizationException('Only owner can manage team profiles.');
        }
    }
}
