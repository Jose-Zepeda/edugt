<?php

namespace App\Domain\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpLedgerEntry extends Model
{
    protected $fillable = [
        'user_id', 'delta', 'source', 'reason', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Users\Models\User::class);
    }
}
