<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeetingDatabaseChannel extends Notification
{
    public function send(object $notifiable, Notification $notification): void
    {
        // $notification->meeting と指定して、未定義エラーを修正しました
        $timingLabel = $notification->window === 'eve' ? '明日' : '1時間前';

        DB::table('notifications')->insert([
            'id' => (string) Str::ulid(),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'data' => json_encode([
                'meeting_id' => $notification->meeting->id,
                'title' => '面談リマインダー',
                'body' => "予約済みの面談の{$timingLabel}になりました（開始日時: ".$notification->meeting->scheduled_at->format('H:i').'）。',
                'notification_type' => 'meeting_reminder',
            ], JSON_UNESCAPED_UNICODE),
            'link_url' => '/meetings/'.$notification->meeting->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
