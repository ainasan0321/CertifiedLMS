<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\NotificationCreatedMail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Mail;

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
            'notifiable_type' => User::class,
            'notifiable_id' => $targetUser->id,
            'link_url' => $linkUrl,
            'data' => [
                'title' => $title,
                'notification_type' => $type,
                'body' => $body,
            ],
        ]);

        if ($targetUser->email) {
            Mail::to($targetUser->email)->send(new NotificationCreatedMail($notification));
        }

        return $notification;
    }
}
