<?php

namespace App\Domain\Classrooms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Users\Models\User;

class ClassroomStudent extends Model
{
    protected $fillable = [
        'classroom_id', 'student_id', 'joined_at', 'state',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
