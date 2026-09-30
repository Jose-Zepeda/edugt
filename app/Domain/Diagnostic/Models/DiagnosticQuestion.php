<?php

namespace App\Domain\Diagnostic\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticQuestion extends Model
{
    protected $fillable = [
        'diagnostic_test_id', 'dimension', 'statement', 'options',
        'correct_index', 'explanation', 'order_index',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(DiagnosticTest::class, 'diagnostic_test_id');
    }
}
