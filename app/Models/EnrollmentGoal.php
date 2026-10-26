<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class EnrollmentGoal extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'title',
        'target_date',
        'description',
        'achieved_at'
    ];

    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime'
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
