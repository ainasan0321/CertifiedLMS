<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory, HasUlids;

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
}
