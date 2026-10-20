<?php

namespace App\Domain\Classrooms\Actions;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\ClassroomCode;
use App\Domain\Classrooms\Models\ClassroomStudent;
use App\Domain\Users\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RF-19: aulas virtuales y códigos de inscripción.
 */
final readonly class ManageClassrooms
{
    public function create(User $teacher, array $data): Classroom
    {
        abort_unless($teacher->isTeacher() && $teacher->status === 'active', 403, 'SOLO_DOCENTE_ACTIVO');

        return DB::transaction(function () use ($teacher, $data): Classroom {
            $classroom = Classroom::query()->create([
                'teacher_id' => $teacher->id,
                'name' => $data['name'],
                'institution' => $data['institution'] ?? null,
                'grade_level' => $data['grade_level'] ?? null,
                'academic_year' => $data['academic_year'],
                'is_active' => true,
            ]);

            $this->generateCode($classroom);

            return $classroom;
        });
    }

    /** Regenerar revoca el código anterior (Fase 8: criterio de aceptación). */
    public function generateCode(Classroom $classroom): ClassroomCode
    {
        return DB::transaction(function () use ($classroom): ClassroomCode {
            ClassroomCode::query()
                ->where('classroom_id', $classroom->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'revoked_at' => now()]);

            return ClassroomCode::query()->create([
                'classroom_id' => $classroom->id,
                'code' => strtoupper(Str::random(8)),
                'is_active' => true,
            ]);
        });
    }

    /** Inscripción del estudiante por código (RF-19). Idempotente: re-inscribir reactiva. */
    public function join(User $student, string $code): ClassroomStudent
    {
        abort_unless($student->isStudent(), 403, 'SOLO_ESTUDIANTE');

        $classroomCode = ClassroomCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($classroomCode === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'El código de inscripción no es válido.',
                'code' => 'INVALID_CLASSROOM_CODE',
            ], 422));
        }

        $membership = ClassroomStudent::query()
            ->where('classroom_id', $classroomCode->classroom_id)
            ->where('student_id', $student->id)
            ->first();

        if ($membership !== null) {
            if ($membership->state === 'active') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ya estás inscrito en esta aula.',
                    'code' => 'ALREADY_ENROLLED',
                ], 409));
            }

            $membership->forceFill(['state' => 'active', 'joined_at' => now()])->save();

            return $membership->refresh();
        }

        return ClassroomStudent::query()->create([
            'classroom_id' => $classroomCode->classroom_id,
            'student_id' => $student->id,
            'joined_at' => now(),
            'state' => 'active',
        ]);
    }

    /** Activar / archivar / retirar estudiantes (RF-19). */
    public function setState(User $teacher, int $classroomId, int $studentId, string $state): ClassroomStudent
    {
        abort_unless(in_array($state, ['active', 'archived', 'removed'], true), 422, 'ESTADO_INVALIDO');

        $classroom = Classroom::query()->where('teacher_id', $teacher->id)->findOrFail($classroomId);

        $membership = ClassroomStudent::query()
            ->where('classroom_id', $classroom->id)
            ->where('student_id', $studentId)
            ->firstOrFail();

        $membership->forceFill(['state' => $state])->save();

        return $membership->refresh();
    }
}
