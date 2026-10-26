<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\QaThreadStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaThread extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'is_resolved',
        'status',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies() :HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }


}
