<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pretest/Postest (§3.5) — FUERA de gamificación (RB-11)
        Schema::create('diagnostic_tests', function (Blueprint $table) {
            $table->id();
            $table->string('kind');                            // pre|post
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('question_count');          // 12..20
            $table->unsignedInteger('time_limit_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'version']);               // RB-17: postest comparable con pretest
        });

        Schema::create('diagnostic_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete();
            $table->string('dimension');                        // abstraction|decomposition|pattern_recognition|algorithms|variables|conditionals|loops
            $table->text('statement');
            $table->json('options');                            // [texto, ...]
            $table->unsignedTinyInteger('correct_index');       // correcto SOLO en servidor (RB-10)
            $table->text('explanation')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
            $table->index(['diagnostic_test_id', 'dimension']);
        });

        // Intentos: UNA sola presentación por usuario y kind (RF-6)
        Schema::create('diagnostic_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_test_id')->constrained()->cascadeOnDelete();
            $table->string('kind');                             // denormalizado para comparar pre/post rápido
            $table->json('dimension_scores')->nullable();       // {dimension: {correct, total}}
            $table->unsignedTinyInteger('score_total')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('in_progress');   // in_progress|completed|abandoned
            $table->timestamps();
            $table->unique(['user_id', 'kind']);                // una sola presentación por kind
        });

        // Respuestas guardadas una a una → reanudar tras interrupción (RF-28)
        Schema::create('diagnostic_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('selected_index')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['diagnostic_attempt_id', 'diagnostic_question_id'], 'diag_answer_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_answers');
        Schema::dropIfExists('diagnostic_attempts');
        Schema::dropIfExists('diagnostic_questions');
        Schema::dropIfExists('diagnostic_tests');
    }
};
