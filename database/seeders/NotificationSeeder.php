<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Mail\NotificationCreatedMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Mail::fake();

        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        $unreadTarget = 15;
        $readTarget = 15;

        $studentCoachTypes = [[
            'title' => '新着チャットメッセージ',
            'type' => 'chat_message_received',
            'body' => '担当者から新しいメッセージが届いています。',
            'url' => '/chat-rooms',
        ],
        [
            'title' => '質問に新しい回答が届きました',
            'type' => 'qa_reply_received',
            'body' => 'あなたの投稿した質問に新着の回答があります。',
            'url' => '/qa-board',
        ],
        [
            'title' => '定期面談の予約が確定しました',
            'type' => 'meeting_reserved',
            'body' => '面談の予約が確定しました。スケジュールをご確認ください。',
            'url' => '/meetings',
        ],
        [
            'title' => '面談がキャンセルされました',
            'type' => 'meeting_canceled',
            'body' => '予約されていた面談がキャンセルされました。',
            'url' => '/meetings',
        ],
        ];

        $adminTypes = [
            [
                'title' => 'システムメンテナンスのお知らせ',
                'type' => 'system_announcement',
                'body' => '定期メンテナンスの予定をご確認ください。',
                'url' => '/dashboard',
            ],
            [
                'title' => 'プラットフォーム運営通知',
                'type' => 'system_announcement',
                'body' => '管理者向けの運営状況サマリが更新されました。',
                'url' => '/dashboard',
            ],
        ];

        foreach ($users as $user) {
            $types = $user->role === UserRole::Admin ? $adminTypes : $studentCoachTypes;
            $typeCount = count($types);
            $totalCreated = 0;

            // 未読通知を指定件数15件作成
            for ($i = 1; $i <= $unreadTarget; $i++) {
                $totalCreated++;
                $base = $types[$totalCreated % $typeCount];

                $notification = Notification::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'link_url' => $base['url'],
                    'data' => [
                        'title' => $base['title']." (未読 #{$i})",
                        'notification_type' => $base['type'],
                        'body' => $base['body'],
                    ],
                    'read_at' => null,
                    'created_at' => now()->subDays($totalCreated),
                ]);

                if ($user->email) {
                    Mail::to($user->email)->send(new NotificationCreatedMail($notification));
                }
            }

            // 既読通知15件
            for ($j = 1; $j <= $readTarget; $j++) {
                $totalCreated++;
                $base = $types[$totalCreated % $typeCount];

                $notification = Notification::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'link_url' => $base['url'],
                    'data' => [
                        'title' => $base['title'],
                        'notification_type' => $base['type'],
                        'body' => $base['body'],
                    ],
                    'read_at' => now()->subDays($totalCreated)->addHours(2),
                    'created_at' => now()->subDays($totalCreated),
                ]);
            }
        }
    }
}
