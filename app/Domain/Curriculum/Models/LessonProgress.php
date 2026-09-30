<?php

namespace App\Domain\Curriculum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Users\Models\User;

class LessonProgress extends Model
{
    protected $fillable = [
        'user_id', 'lesson_id', 'started_at', 'last_visited_at',
        'time_spent_seconds', 'completed', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_visited_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
