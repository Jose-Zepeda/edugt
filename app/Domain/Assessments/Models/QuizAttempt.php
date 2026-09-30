<?php

namespace App\Domain\Assessments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Users\Models\User;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id', 'quiz_id', 'attempt_number', 'started_at',
        'submitted_at', 'score', 'status', 'xp_awarded', 'xp_amount',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'quiz_attempt_id');
    }
}
