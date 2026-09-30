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

    /** GET /api/v1/classrooms (aulas del docente autenticado) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $classrooms = Classroom::query()
            ->where('teacher_id', $user->id)
            ->withCount(['students'])
            ->get();

        return response()->json(['data' => $classrooms->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'academic_year' => $c->academic_year,
            'students_count' => $c->students_count,
            'code' => $c->activeCode()->first()?->code,
        ])]);
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
