<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Classrooms\Actions\ManageClassrooms;
use App\Domain\Classrooms\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-19, RF-20: aulas para docentes; join para estudiantes.
 */
final class ClassroomController extends Controller
{
    public function __construct(private readonly ManageClassrooms $manage) {}

    /** POST /api/v1/classrooms (teacher activo) */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isTeacher() && $user->status === 'active', 403, 'TEACHER_PENDING_APPROVAL');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:160'],
            'grade_level' => ['nullable', 'string', 'max:80'],
            'academic_year' => ['required', 'string', 'max:9'],
        ]);

        $classroom = ($this->manage)->create($user, $data);

        return response()->json([
            'data' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'code' => $classroom->activeCode()->first()?->code,
            ],
        ], 201);
    }

    /** GET /api/v1/classrooms (aulas del docente autenticado o aulas inscritas del estudiante) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $classrooms = Classroom::query()
                ->whereHas('students', fn ($q) => $q->where('student_id', $user->id)->where('state', 'active'))
                ->with(['teacher:id,name,email'])
                ->get();

            return response()->json(['data' => $classrooms->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'institution' => $c->institution,
                'grade_level' => $c->grade_level,
                'academic_year' => $c->academic_year,
                'teacher_name' => $c->teacher?->name,
            ])]);
        }

        $classrooms = Classroom::query()
            ->where('teacher_id', $user->id)
            ->withCount(['students'])
            ->get();

        return response()->json(['data' => $classrooms->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'institution' => $c->institution,
            'grade_level' => $c->grade_level,
            'academic_year' => $c->academic_year,
            'students_count' => $c->students_count,
            'code' => $c->activeCode()->first()?->code,
        ])]);
    }

    /** PUT /api/v1/classrooms/{id} (docente dueño o admin) */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'institution' => ['nullable', 'string', 'max:160'],
            'grade_level' => ['nullable', 'string', 'max:80'],
            'academic_year' => ['required', 'string', 'max:9'],
        ]);

        $classroom->update($data);

        return response()->json([
            'data' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'institution' => $classroom->institution,
                'grade_level' => $classroom->grade_level,
                'academic_year' => $classroom->academic_year,
                'code' => $classroom->activeCode()->first()?->code,
            ],
            'message' => 'Aula actualizada correctamente.',
        ]);
    }

    /** DELETE /api/v1/classrooms/{id} (docente dueño o admin) */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $classroom->delete();

        return response()->json(['message' => 'Aula eliminada correctamente.']);
    }

    /** POST /api/v1/classrooms/{id}/codes/regenerate (docente dueño) */
    public function regenerateCode(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->where('teacher_id', $user->id)->findOrFail($id);

        $code = ($this->manage)->generateCode($classroom);

        return response()->json(['code' => $code->code]);
    }

    /** POST /api/v1/classrooms/join {code} (student) */
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        $membership = ($this->manage)->join($user, $data['code']);

        return response()->json([
            'classroom_id' => $membership->classroom_id,
            'state' => $membership->state,
        ], 201);
    }

    /** GET /api/v1/classrooms/{id}/students (docente dueño o admin) */
    public function students(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $classroom = Classroom::query()->findOrFail($id);

        abort_unless($user->isAdmin() || $classroom->teacher_id === $user->id, 403, 'FORBIDDEN');

        $rows = $classroom->students()->with('student')->get();

        return response()->json(['data' => $rows->map(fn ($m) => [
            'student_id' => $m->student_id,
            'name' => $m->student->name,
            'email' => $m->student->email,
            'learning_track' => $m->student->learning_track,
            'joined_at' => $m->joined_at?->toIso8601String(),
            'state' => $m->state,
        ])]);
    }

    /** POST /api/v1/classrooms/{id}/students/{studentId}/state {state} */
    public function setStudentState(Request $request, int $id, int $studentId): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['state' => ['required', 'in:active,archived,removed']]);

        $membership = ($this->manage)->setState($user, $id, $studentId, $data['state']);

        return response()->json(['state' => $membership->state]);
    }
}
