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
        $student = User::where('role', 'student')->first();
        $coach = User::where('role', 'coach')->first();

        // 該当するユーザーがいない場合は、先頭のユーザーを代用
        $student = $student ?? User::first();
        $coach = $coach ?? User::where('id', '!=', $student?->id)->first() ?? $student;

        if (!$student) {
            $this->command->warn('ユーザーが存在しないため、通知初期データの投入をスキップしました。先にUserSeederを実行してください。');
            return;
        }

        $notificationPatterns = [
            [
                'link_url' => '/notifications',
                'data' => [
                    'title' => '質問に新しい回答が届きました',
                    'notification_type' => 'qa_reply_received',
                    'body' => '「Laravelのルーティングについて」の質問にコーチから回答が投稿されました。',
                ]
            ],
            [
                'link_url' => '/notifications',
                'data' => [
                    'title' => '新着チャットメッセージ',
                    'notification_type' => 'chat_message_received',
                    'body' => '担当コーチから新しいチャットメッセージが届いています。',
                ]
            ],
            [
                'link_url' => '/notifications',
                'data' => [
                    'title' => '定期面談の予約が確定しました',
                    'notification_type' => 'meeting_reserved',
                    'body' => '次回面談の日時が 2026/10/15 14:00 に確定しました。',
                ]
            ],
        ];

        // コーチへの確認後に件数をいつでも変更できるよう、変数で件数を定義
        // 1ページ20件のページネーションを確認するため、25件ずつ投入します
        $targetCount = 25;

        foreach ([$student, $coach] as $user) {
            for ($i = 1; $i <= $targetCount; $i++) {
                // ランダムな通知パターンを選択
                $pattern = $notificationPatterns[array_rand($notificationPatterns)];
                
                // ループの回数に応じて、既読(read_atに日時あり)と未読(null)を混在させる
                $readAt = ($i % 3 === 0) ? now()->subHours($i) : null;

                Notification::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'link_url' => $pattern['link_url'],
                    'data' => [
                        'title' => $pattern['data']['title'] . " (#{$i})",
                        'notification_type' => $pattern['data']['notification_type'],
                        'body' => $pattern['data']['body'] . "（これは表示確認用のダミー通知です）",
                    ],
                    'read_at' => $readAt,
                    'created_at' => now()->subDays($i), // ページネーション順の確認用に作成日時をずらす
                ]);
            }
        }

        $this->command->info("受講生とコーチへそれぞれ {$targetCount} 件ずつの通知初期データを投入しました。");
    }
}