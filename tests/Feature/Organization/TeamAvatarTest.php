<?php

namespace Tests\Feature\Organization;

use App\Actions\Company\CreateCompanyUser;
use App\Actions\Company\UpdateCompanyUserProfile;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_staff_with_avatar_and_avatar_is_viewable_within_company(): void
    {
        Storage::fake('local');
        [$company, $department] = $this->createCompanyContext('Team Avatar A');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $avatarFile = $this->makeTestPngUpload();

        app(CreateCompanyUser::class)($owner, [
            'name' => 'Avatar Staff',
            'email' => 'avatar-staff@example.test',
            'phone' => '08012340000',
            'gender' => 'female',
            'password' => 'password123',
            'role' => UserRole::Staff->value,
            'department_id' => $department->id,
            'reports_to_user_id' => $owner->id,
            'avatar' => $avatarFile,
        ]);

        $staff = User::query()
            ->where('company_id', $company->id)
            ->where('email', 'avatar-staff@example.test')
            ->first();

        $this->assertNotNull($staff);
        $this->assertNotNull($staff->avatar_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $staff->avatar_path));

        $this->actingAs($owner);
        $response = $this->get(route('users.avatar', ['user' => $staff->id]));
        $response->assertOk();
    }

    public function test_avatar_route_is_blocked_across_companies(): void
    {
        Storage::fake('local');
        [$companyA, $departmentA] = $this->createCompanyContext('Team Avatar B');
        [$companyB, $departmentB] = $this->createCompanyContext('Team Avatar C');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);
        $staffA = $this->createUser($companyA, $departmentA, UserRole::Staff->value);

        $staffA->forceFill([
            'avatar_path' => 'private/avatars/'.$companyA->id.'/'.$staffA->id.'/staff-avatar.jpg',
        ])->save();

        Storage::disk('local')->put($staffA->avatar_path, 'fake-image-binary');

        $this->actingAs($ownerB)
            ->get(route('users.avatar', ['user' => $staffA->id]))
            ->assertNotFound();

        $this->actingAs($ownerA)
            ->get(route('users.avatar', ['user' => $staffA->id]))
            ->assertOk();
    }

    public function test_owner_can_update_staff_profile_and_remove_avatar(): void
    {
        Storage::fake('local');
        [$company, $department] = $this->createCompanyContext('Team Avatar D');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $staff->forceFill([
            'avatar_path' => 'private/avatars/'.$company->id.'/'.$staff->id.'/staff-avatar.jpg',
            'gender' => 'male',
        ])->save();

        Storage::disk('local')->put((string) $staff->avatar_path, 'fake-image-binary');

        app(UpdateCompanyUserProfile::class)($owner, $staff->fresh(), [
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.test',
            'phone' => '08000000123',
            'gender' => 'female',
            'remove_avatar' => true,
        ]);

        $staff->refresh();

        $this->assertSame('Updated Staff', $staff->name);
        $this->assertSame('updated.staff@example.test', $staff->email);
        $this->assertSame('female', $staff->gender);
        $this->assertNull($staff->avatar_path);
        $this->assertFalse(Storage::disk('local')->exists('private/avatars/'.$company->id.'/'.$staff->id.'/staff-avatar.jpg'));
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function createUser(Company $company, Department $department, string $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeTestPngUpload(): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'avatar_');
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8N9moAAAAASUVORK5CYII=',
            true
        );

        file_put_contents($tmpPath, $pngBinary !== false ? $pngBinary : '');

        return new UploadedFile(
            path: $tmpPath,
            originalName: 'staff-avatar.png',
            mimeType: 'image/png',
            test: true,
        );
    }
}
