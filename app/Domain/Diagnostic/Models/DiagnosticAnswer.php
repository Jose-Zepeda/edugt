<?php

namespace App\Domain\Diagnostic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticAnswer extends Model
{
    protected $fillable = [
        'diagnostic_attempt_id', 'diagnostic_question_id', 'selected_index', 'is_correct', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(DiagnosticAttempt::class, 'diagnostic_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(DiagnosticQuestion::class, 'diagnostic_question_id');
    }
}
