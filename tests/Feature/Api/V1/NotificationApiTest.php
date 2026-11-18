<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'link_url' => '/dashboard',
            'data' => [
                'title' => 'テスト通知',
                'notification_type' => 'system',
                'body' => '通知本文のプレビューです。',
            ],
            'read_at' => null,
        ], $overrides));
    }

    public function test_unauthenticated_user_cannot_access_notifications_api(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_admin_user_cannot_access_notifications_api(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->getJson('/api/v1/notifications')
            ->assertForbidden();
    }

    public function test_student_and_coach_can_fetch_only_their_own_notifications(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $studentNotification = $this->createNotification($student, [
            'data' => ['title' => '受講生宛', 'body' => '本文1', 'notification_type' => 'system'],
        ]);
        $this->createNotification($coach, [
            'data' => ['title' => 'コーチ宛', 'body' => '本文2', 'notification_type' => 'system'],
        ]);

        $this->actingAs($student)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.id', $studentNotification->id)
            ->assertJsonPath('notifications.0.title', '受講生宛');

        $this->actingAs($coach)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $notification = $this->createNotification($student);

        $this->actingAs($student)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $studentA = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $studentB = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $notificationB = $this->createNotification($studentB);

        $this->actingAs($studentA)
            ->postJson("/api/v1/notifications/{$notificationB->id}/read")
            ->assertForbidden();

        $this->assertNull($notificationB->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $other = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $this->createNotification($student);
        $this->createNotification($student);
        $otherNotification = $this->createNotification($other);

        $this->actingAs($student)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertEquals(0, Notification::where('notifiable_id', $student->id)->unread()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_stateful_post_without_csrf_token_is_rejected(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
        $notification = $this->createNotification($student);

        $this->get('/sanctum/csrf-cookie');

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            $this->actingAs($student)
                ->withHeaders([
                    'Referer' => config('app.url'),
                    'Accept' => 'application/json',
                ])
                ->post("/api/v1/notifications/{$notification->id}/read")
                ->assertStatus(419);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
