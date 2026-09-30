<?php

namespace App\Domain\Curriculum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'mission_id', 'title', 'description', 'required_level', 'order_index', 'status',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order_index');
    }

    public function quiz()
    {
        return $this->hasOne(\App\Domain\Assessments\Models\Quiz::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
