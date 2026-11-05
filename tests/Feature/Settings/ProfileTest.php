<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_student_can_update_profile(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
            'name' => '初期名前',
            'bio' => '初期自己紹介',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => '新しい名前',
                'bio' => '新しい自己紹介',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '新しい名前',
            'bio' => '新しい自己紹介',
        ]);
    }

    public function test_profile_update_validation_max_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student]);

        $longName = str_repeat('あ', 51);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $longName,
                'bio' => '自己紹介',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $response->assertSessionHasErrors(['name']);
    }

    public function test_non_coach_cannot_update_meeting_url(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'meeting_url' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => '受講生',
                'meeting_url' => 'https://google.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'meeting_url' => null,
        ]);
    }

    public function test_avatar_upload_and_old_file_deletion(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => UserRole::Student]);

        $oldFile = UploadedFile::fake()->image('old_avatar.jpg');
        $oldPath = $oldFile->store('avatars', 'public');
        $user->update(['avatar_url' => $oldPath]);

        $newFile = UploadedFile::fake()->image('new_avatar.png');

        $response = $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => $newFile,
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $user->refresh();
        Storage::disk('public')->assertExists($user->getRawOriginal('avatar_url'));
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_password_change_validation_uses_named_error_bag(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Student,
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $response->assertSessionHasErrorsIn('updatePassword', ['current_password']);
    }
}
