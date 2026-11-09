<?php

namespace App\Domain\Lab\Models;

use App\Domain\Curriculum\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabChallenge extends Model
{
    protected $fillable = [
        'module_id',
        'slug',
        'title',
        'description',
        'dimension',
        'difficulty',
        'xp_reward',
        'order_index',
        'simulator_json',
        'challenge_json',
        'solution_json',
        'status',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'order_index' => 'integer',
        'simulator_json' => 'array',
        'challenge_json' => 'array',
        'solution_json' => 'array',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LabAttempt::class, 'lab_challenge_id');
    }
}
