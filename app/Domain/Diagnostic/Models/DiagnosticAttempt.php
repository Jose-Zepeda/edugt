<?php

namespace App\Domain\Diagnostic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Users\Models\User;

class DiagnosticAttempt extends Model
{
    // Campos servidor-side incluidos: solo el dominio los escribe (RB-10)
    protected $fillable = [
        'user_id', 'diagnostic_test_id', 'kind', 'started_at',
        'submitted_at', 'score_total', 'dimension_scores', 'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'dimension_scores' => 'array',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(DiagnosticTest::class, 'diagnostic_test_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DiagnosticAnswer::class, 'diagnostic_attempt_id');
    }
}
