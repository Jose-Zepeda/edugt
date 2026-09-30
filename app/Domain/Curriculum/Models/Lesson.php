<?php

namespace App\Domain\Curriculum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'module_id', 'title', 'content_markdown', 'pseudocode_json', 'images',
        'estimated_minutes', 'xp_reward', 'order_index', 'requires_quiz_pass', 'status',
    ];

    protected function casts(): array
    {
        return [
            'pseudocode_json' => 'array',
            'images' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
