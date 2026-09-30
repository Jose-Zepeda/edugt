<?php

namespace App\Domain\Classrooms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Users\Models\User;

class ClassroomCode extends Model
{
    protected $fillable = [
        'classroom_id', 'code', 'is_active', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }
}
