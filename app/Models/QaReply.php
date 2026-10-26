<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QaReply extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qaThread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class);
    }
}
