<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aulas virtuales (§3.6)
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('institution')->nullable();
            $table->string('grade_level')->nullable();           // cuarto/quinto bachillerato, perito contador
            $table->string('academic_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Códigos de inscripción: uno activo por aula; regenerar revoca el anterior (Fase 8)
        Schema::create('classroom_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'is_active']);
        });

        // Membresías (§3.6)
        Schema::create('classroom_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->string('state')->default('active');          // active|archived|removed
            $table->timestamps();
            $table->unique(['classroom_id', 'student_id']);
            $table->index(['classroom_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_students');
        Schema::dropIfExists('classroom_codes');
        Schema::dropIfExists('classrooms');
    }
};
