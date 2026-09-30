<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Misión → Módulo → Lección (§3.3 del plan)
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('order_index')->default(0);
            $table->string('status')->default('draft');     // draft|published
            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('required_level')->default(1); // sobre xp_level (RB-05)
            $table->unsignedInteger('order_index')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content_markdown')->nullable();
            $table->json('pseudocode_json')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(10);
            $table->unsignedInteger('xp_reward')->default(10);
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('requires_quiz_pass')->default(false);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        // Progreso por usuario/lección (§3.3) — UNIQUE(user, lesson)
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_visited_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('missions');
    }
};
