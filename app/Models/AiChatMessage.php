<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'status',
        'content',
        'model',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
        'error_detail',
    ];

    protected $hidden = [
        'model',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
        'error_detail',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'response_time_ms' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
