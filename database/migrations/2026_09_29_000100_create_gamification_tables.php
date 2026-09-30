<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // XP, nivel XP, racha — un perfil por usuario (§3.2 del plan)
        Schema::create('user_xp_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp_total')->default(0);
            $table->unsignedInteger('xp_level')->default(1);
            $table->unsignedInteger('streak_current')->default(0);
            $table->unsignedInteger('streak_longest')->default(0);
            $table->date('streak_last_active_date')->nullable();
            $table->timestamp('xp_updated_at')->nullable();
            $table->timestamps();
        });

        // Libro contable append-only de XP — la fuente de verdad (§3.2)
        Schema::create('xp_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');                       // positivo o negativo
            $table->string('source');                        // quiz|lesson|streak|manual
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Catálogo de insignias (§3.2)
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('description');
            $table->string('icon')->nullable();
            $table->timestamps();
            $table->softDeletes();                          // retirar de catálogo sin romper historial
        });

        // Insignias otorgadas — UNIQUE evita duplicados (RB-07)
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'badge_id']);
        });

        // Reglas declarativas de insignias (§3.2 — decisión: gestionables por admin vía BD)
        Schema::create('badge_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->string('event');                        // quiz_passed|lessons_completed|streak_days|...
            $table->json('conditions')->nullable();         // {"module_id": 3, "count": 5}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_rules');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('xp_ledger_entries');
        Schema::dropIfExists('user_xp_profiles');
    }
};
