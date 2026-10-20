<?php

namespace App\Domain\Gamification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Users\Models\User;

class UserXpProfile extends Model
{
    protected $fillable = [
        'user_id', 'xp_total', 'xp_level', 'streak_current', 'streak_longest', 'streak_last_active_date',
    ];

    protected function casts(): array
    {
        return [
            'streak_last_active_date' => 'date',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(XpLedgerEntry::class, 'user_id', 'user_id');
    }
}
