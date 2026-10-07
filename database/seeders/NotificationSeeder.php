<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // テスト対象となる受講生(student)とコーチ(coach)をデータベースから取得
        $student = User::where('role', 'student')->first() ?? User::first();
        $coach = User::where('role', 'coach')->first() ?? User::where('id','!=', $student?->id)->first() ?? $student;

        if (!$student) {
            return;
        }

        $unreadTarget = 8;
        $readTarget = 7;

        $types = [[
                'title' => '新着チャットメッセージ',
                'type' => 'chat_message_received',
                'body' => '担当者から新しいメッセージが届いています。',
                'url' => '/chat',
            ],
            [
                'title' => '質問に新しい回答が届きました',
                'type' => 'qa_reply_received',
                'body' => 'あなたの投稿した質問に新着の回答があります。',
                'url' => '/qa',
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

        foreach ([$student, $coach] as $user) {
            $totalCreated = 0;

            // 未読通知を指定件数15件作成
            for ($i = 1; $i <= $unreadTarget; $i++) {
                $totalCreated++;
                $base = $types[$totalCreated % 4];

                $notification = Notification::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'link_url' => $base['url'],
                    'data' => [
                        'title' => $base['title'] . " (未読 #{$i})",
                        'notification_type' => $base['type'],
                        'body' => $base['body'],
                    ],
                    'read_at' => null,
                    'created_at' => now()->subDays($totalCreated),
                ]);

                if ($user->email) {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\NotificationCreatedMail($notification));
                }
            }

            // 既読通知15件
            for($j = 1; $j <= $readTarget; $j++) {
                $totalCreated++;
                $base = $types[$totalCreated % 4];

                $notification = Notification::create([
                    'notifiable_type' => User::class,
                    'notifiable_id'=> $user->id,
                    'link_url' => $base['url'],
                    'data' => [
                        'title' => $base['title'],
                        'notification_type' => $base['type'],
                        'body' => $base['body'],
                    ],
                    'read_at' => now()->subDays($totalCreated)->addHours(2),
                    'created_at' => now()->subDays($totalCreated),
                ]);

                if ($user->email) {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\NotificationCreatedMail($notification));
                }
            }
        }
    }
}