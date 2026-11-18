<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\NotificationCreatedMail;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class QueueNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_test_notification_mail_is_queued_instead_of_sent_synchronously(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'student@example.com']);

        Notification::sendNotification(
            $user,
            'テスト通知',
            'chat',
            'メッセージ本文',
            '/chat'
        );

        // 即時送信（assertSent）ではなくキュー投入（assertQueued）されること
        Mail::assertQueued(NotificationCreatedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_announcement_queues_mail_for_all_target_students(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
            'status' => UserStatus::InProgress->value,
        ]);
        $student1 = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
            'email' => 's1@example.com',
        ]);
        $student2 = User::factory()->create([
            'role' => UserRole::Student->value,
            'status' => UserStatus::InProgress->value,
            'email' => 's2@example.com',
        ]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '一斉お知らせ',
            'body' => '本文テスト',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        Mail::assertQueued(NotificationCreatedMail::class, 2);
        Mail::assertQueued(NotificationCreatedMail::class, fn ($mail) => $mail->hasTo($student1->email));
        Mail::assertQueued(NotificationCreatedMail::class, fn ($mail) => $mail->hasTo($student2->email));
    }

    public function test_mail_is_not_queued_when_transaction_rolls_back(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create(['email' => 'rollback@example.com']);

        try {
            DB::transaction(function () use ($user) {
                Notification::sendNotification(
                    $user,
                    'ロールバックテスト',
                    'chat',
                    '本文',
                    '/chat'
                );

                throw new \RuntimeException('意図的なロールバック');
            });
        } catch (\RuntimeException) {
            // 例外を握りつぶす
        }

        // トランザクション失敗時はキューに積まれないこと
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_meeting_reminder_notification_implements_should_queue(): void
    {
        NotificationFacade::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->create();

        $notification = new MeetingReminderNotification($meeting, 'eve');
        $this->assertInstanceOf(ShouldQueue::class, $notification);

        $user->notify($notification);

        NotificationFacade::assertSentTo($user, MeetingReminderNotification::class);
    }
}
