<?php

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Models\Notification;
use App\Mail\NotificationCreatedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;

    public function test_notification_mail_is_constructed_correctly(): void
    {
        Mail::fake();

        $user = User::factory()->student()->create();

        $notification = Notification::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'タイトル通知の例',
                'body' => '本文の例'
            ]
        ]);

        $mail = new NotificationCreatedMail($notification);

        $envelope = $mail->envelope();
        $this->assertEquals('[Certify LMS] <通知タイトル>タイトル通知の例', $envelope->subject);

        $content = $mail->content();
        $this->assertEquals('emails.notification_created', $content->markdown);
        $this->assertEquals($notification, $content->with['notification']);
    }
}
