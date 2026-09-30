<?php

namespace App\Domain\Diagnostic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticTest extends Model
{
    protected $table = 'diagnostic_tests';

    protected $fillable = [
        'kind', 'version', 'question_count', 'time_limit_minutes', 'is_active',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(DiagnosticQuestion::class)->orderBy('order_index');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfKind($query, string $kind)
    {
        return $query->where('kind', $kind)->where('is_active', true);
    }
}
