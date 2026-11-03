<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AnnouncementTargetType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'body',
        'target_type',
        'certification_id',
        'user_id',
        'dispatched_count',
        'dispatched_at',
        'created_by',
    ];

    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'dispatched_at' => 'datetime',
    ];

    // 資格
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'certification_id');
    }

    // 配信するユーザー
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 配信したユーザー
    public function createdby(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
