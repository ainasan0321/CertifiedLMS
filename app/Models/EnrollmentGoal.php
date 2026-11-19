<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentGoal extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'title',
        'target_date',
        'description',
        'achieved_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    /**
     * 未達成を先頭にし、目標期日が近い順（期日未設定は末尾）、同条件は新しく作成した順に並べる。
     */
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('achieved_at IS NOT NULL ASC')
            ->orderByRaw('target_date IS NULL ASC')
            ->orderBy('target_date', 'asc')
            ->orderByDesc('created_at');
    }
}
