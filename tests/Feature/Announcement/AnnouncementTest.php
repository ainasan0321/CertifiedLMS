<?php

namespace Tests\Feature\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;

    private User $admin;
    private User $student1;
    private User $student2;
    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = \App\Models\User::factory()->create([
            'role' => \App\Enums\UserRole::Admin->value,
            'status' => \App\Enums\UserStatus::InProgress->value,
        ]);

        $this->student1 = \App\Models\User::factory()->create([
            'role' => \App\Enums\UserRole::Student->value,
            'status' => \App\Enums\UserStatus::InProgress->value,
        ]);

        $this->student2 = \App\Models\User::factory()->create([
            'role' => \App\Enums\UserRole::Student->value,
            'status' => \App\Enums\UserStatus::InProgress->value,
        ]);
        
        $this->certification = \App\Models\Certification::factory()->create();

        Enrollment::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $this->student1->id,
            'certification_id' => $this->certification->id,
            'status' => 'learning',
        ]);

        if (method_exists($this->student1, 'assignedCertifications')) {
            $this->student1->certifications()->attach($this->certification->id, ['id' => (string) \Illuminate\Support\Str::ulid()]);
        }
    }

    // 全受講生向けのお知らせ配信が正常に完了し、全員に通知が届くこと
    public function test_admin_can_dispatch_announcement_to_all_students(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '全体連絡タイトル',
                'body' => '全体連絡本文',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        $response->assertRedirect(route('announcements.management.index'));

        // お知らせ履歴の検証
        $this->assertDatabaseHas('announcements', [
            'title' => '全体連絡タイトル',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 2,
            'created_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->student1->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->student2->id]);
    }
    
    // 【管理者】資格指定のお知らせ配信が正常に完了し、対象者のみに通知が届くこと
    public function test_admin_can_dispatch_announcement_to_specific_certification(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '資格限定タイトル',
                'body' => '資格限定本文',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $this->certification->id,
            ]);

        $response->assertRedirect(route('announcements.management.index'));

        // 配信件数は資格を持つ student1 のみの「1件」になること
        $this->assertDatabaseHas('announcements', [
            'title' => '資格限定タイトル',
            'target_type' => AnnouncementTargetType::Certification->value,
            'dispatched_count' => 1,
        ]);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->student1->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->student2->id]);
    }

    // 【管理者】ユーザー指定のお知らせ配信が正常に完了し、指定された1人のみに通知が届くこと
    public function test_admin_can_dispatch_announcement_to_specific_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'title' => '個別連絡タイトル',
                'body' => '個別連絡本文',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $this->student2->id,
            ]);

        $response->assertRedirect(route('announcements.management.index'));

        $this->assertDatabaseHas('announcements', [
            'title' => '個別連絡タイトル',
            'target_type' => AnnouncementTargetType::User->value,
            'dispatched_count' => 1,
            'user_id' => $this->student2->id,
        ]);

        // student2（指定）には届き、student1（非指定）には届かないこと
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->student2->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->student1->id]);
    }

    // 【管理者】配信対象の受講生が0人の場合、エラーで元の画面に押し戻されること
    public function test_admin_cannot_dispatch_announcement_when_target_count_is_zero(): void
    {
        $emptyCertification = Certification::factory()->create();

        $response = $this->actingAs($this->admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '誰もいない資格タイトル',
                'body' => '本文',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $emptyCertification->id,
            ]);

        // 元の作成画面にリダイレクトされ、エラーメッセージがセッションにあること
        $response->assertRedirect(route('admin.announcements.create'));
        $response->assertSessionHasErrors(['target_type']);
    }

    // 【受講生】お知らせ通知をクリックした際、詳細表示に遷移して正しく既読化されること
    public function test_student_can_view_announcement_notification_and_it_becomes_read(): void
    {
        $announcement = Announcement::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'title' => '運営からの重要なお知らせ',
            'body' => 'お知らせの全文内容です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 1,
            'created_by' => $this->admin->id,
        ]);

        $notification = Notification::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'notifiable_type' => User::class,
            'notifiable_id' => $this->student1->id,
            'data' => [
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'notification_type' => 'admin_announcement',
            ],
            'read_at' => null,
        ]);

        // 1. 通知一覧からクリックした時のフォーム送信テスト
        $response = $this->actingAs($this->student1)
            ->post(route('notifications.markAsRead', $notification));

        // show メソッドへリダイレクトされること
        $response->assertRedirect(route('notifications.show', $notification));

        // 既読の検証
        $this->assertNotNull($notification->refresh()->read_at);

        // 2. 詳細画面を開いたテスト
        $showResponse = $this->actingAs($this->student1)
            ->get(route('notifications.show', $notification));

        $showResponse->assertStatus(200);
        $showResponse->assertViewIs('notifications.show');
        $showResponse->assertSee('運営からの重要なお知らせ');
        $showResponse->assertSee('お知らせの全文内容です。');
    }

    // 受講生】他人の通知詳細画面にアクセスした際、403エラーで厳重にブロックされること
    public function test_student_cannot_view_others_notification(): void
    {
        $announcement = Announcement::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'title' => '全体タイトル',
            'body' => '全体本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 1,
            'created_by' => $this->admin->id,
        ]);

        // student2 宛ての通知を作成
        $otherNotification = Notification::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'notifiable_type' => User::class,
            'notifiable_id' => $this->student2->id,
            'data' => [
                'announcement_id' => $announcement->id,
                'title' => '他人のタイトル',
                'body' => '他人の本文',
                'notification_type' => 'admin_announcement',
            ],
            'read_at' => null,
        ]);

        // student1 が他人の通知にアクセス
        $response = $this->actingAs($this->student1)
            ->get(route('notifications.show', $otherNotification));

        $response->assertStatus(403);
    }
}