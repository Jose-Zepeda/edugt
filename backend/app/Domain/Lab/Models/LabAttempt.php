<?php

namespace App\Domain\Lab\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'lab_challenge_id',
        'completed',
        'attempts_count',
        'best_score',
        'xp_awarded',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'attempts_count' => 'integer',
        'best_score' => 'integer',
        'xp_awarded' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(LabChallenge::class, 'lab_challenge_id');
    }
}
