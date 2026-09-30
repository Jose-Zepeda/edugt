<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quizzes Card-Flow (§3.4)
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('passing_score')->default(70); // RB-05: mínimo 70/100
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('statement');                       // enunciado en español
            $table->string('type')->default('single');        // single|true_false
            $table->text('explanation')->nullable();          // feedback de pregunta (RF-13)
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        // Opciones: is_correct y feedback viven SOLO en servidor (RB-10)
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->text('feedback')->nullable();             // explicación inmediata por opción (RF-13)
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });

        // Intentos con ordinal en servidor (RB-06)
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');        // ordinal por user+quiz (§3.4)
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 0-100, calculado en servidor
            $table->string('status')->default('in_progress'); // in_progress|submitted|abandoned
            $table->boolean('xp_awarded')->default(false);
            $table->unsignedInteger('xp_amount')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'quiz_id', 'attempt_number']);
            $table->index(['quiz_id', 'status']);
        });

        // Historial de respuestas para analytics (RF-21)
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->unique(['quiz_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
    }
};
