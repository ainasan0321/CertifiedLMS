<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'data',
        'link_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        $this->morphTo();
    }

    // 未読通知のみに絞りこむスコープ
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // 共通メソッド】アプリ内通知の保存 ＋ メール送信を1発で行うヘルパー
    public static function sendNotification(User $targetUser, string $title, string $type, $body, string $linkUrl): self
    {
        $notification = self::create([
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $targetUser->id,
            'link_url' => $linkUrl,
            'data' => [
                'title' => $title,
                'notification_type' => $type,
                'body' => $body,
            ],
        ]);

        if ($targetUser->email) {
            \Illuminate\Support\Facades\Mail::to($targetUser->email)->send(new \App\Mail\NotificationCreatedMail($notification));
        }

        return $notification;
    }
}
