<?php

namespace App\Domain\Classrooms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Users\Models\User;

class Classroom extends Model
{
    protected $fillable = [
        'teacher_id', 'name', 'institution', 'grade_level', 'academic_year', 'is_active',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ClassroomCode::class);
    }

    public function activeCode()
    {
        return $this->hasOne(ClassroomCode::class)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->latestOfMany();
    }

    public function students(): HasMany
    {
        return $this->hasMany(ClassroomStudent::class)
            ->where('state', 'active');
    }
}
