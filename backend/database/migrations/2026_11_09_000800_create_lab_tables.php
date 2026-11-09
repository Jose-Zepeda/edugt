<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('dimension')->default('algorithms');
            $table->string('difficulty')->default('beginner');
            $table->unsignedInteger('xp_reward')->default(40);
            $table->unsignedInteger('order_index')->default(0);
            $table->json('simulator_json');
            $table->json('challenge_json');
            $table->json('solution_json');
            $table->string('status')->default('published');
            $table->timestamps();
        });

        Schema::create('lab_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lab_challenge_id')->constrained('lab_challenges')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('best_score')->default(0);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lab_challenge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_attempts');
        Schema::dropIfExists('lab_challenges');
    }
};
