<?php

namespace App\Domain\Curriculum\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'xp_reward', 'order_index', 'status',
    ];

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
