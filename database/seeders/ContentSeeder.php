<?php

namespace Database\Seeders;

use App\Domain\Assessments\Models\Question;
use App\Domain\Assessments\Models\QuestionOption;
use App\Domain\Assessments\Models\Quiz;
use App\Domain\Curriculum\Models\Lesson;
use App\Domain\Curriculum\Models\Module;
use App\Domain\Curriculum\Models\Mission;
use App\Domain\Diagnostic\Models\DiagnosticQuestion;
use App\Domain\Diagnostic\Models\DiagnosticTest;
use App\Domain\Gamification\Models\Badge;
use App\Domain\Gamification\Models\BadgeRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds de contenido base (RF-8, RF-17, RF-28):
 *  - 6 insignias del documento de tesis con reglas declarativas
 *  - 1 misión × 2 módulos × 4 lecciones + quiz por módulo (contrato AGENTS.md §2)
 *  - pretest y postest comparables (RB-17): 14 preguntas, mismas 7 dimensiones
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBadges();
        $this->seedCurriculum();
        $this->seedDiagnostics();
    }

    private function seedBadges(): void
    {
        $badges = [
            ['slug' => 'novato-logica', 'title' => 'Novato en Lógica', 'description' => 'Aprobaste tu primer cuestionario.', 'event' => 'quiz_passed', 'conditions' => ['count' => 1]],
            ['slug' => 'maestro-pseudocodigo', 'title' => 'Maestro del Pseudocódigo', 'description' => 'Aprobaste 5 cuestionarios.', 'event' => 'quiz_passed', 'conditions' => ['count' => 5]],
            ['slug' => 'explorador-variables', 'title' => 'Explorador de Variables', 'description' => 'Completaste el módulo de variables.', 'event' => 'lesson_completed', 'conditions' => ['count' => 4]],
            ['slug' => 'mago-del-control', 'title' => 'Mago del Control', 'description' => 'Dominaste las estructuras condicionales.', 'event' => 'quiz_passed', 'conditions' => ['count' => 3]],
            ['slug' => 'maestro-del-bucle', 'title' => 'Maestro del Bucle', 'description' => 'Dominaste las estructuras cíclicas.', 'event' => 'quiz_passed', 'conditions' => ['count' => 4]],
            ['slug' => 'depurador-elite', 'title' => 'Depurador Élite', 'description' => 'Aprobaste un cuestionario en el primer intento.', 'event' => 'quiz_passed', 'conditions' => ['count' => 1, 'attempt_number' => 1]],
        ];

        foreach ($badges as $b) {
            $badge = Badge::query()->create([
                'slug' => $b['slug'], 'title' => $b['title'], 'description' => $b['description'],
            ]);
            BadgeRule::query()->create([
                'badge_id' => $badge->id,
                'event' => $b['event'],
                'conditions' => $b['conditions'],
                'is_active' => true,
            ]);
        }
    }

    private function seedCurriculum(): void
    {
        $mission = Mission::query()->create([
            'slug' => 'mision-1-fundamentos', 'title' => 'Misión 1: Fundamentos de Lógica',
            'description' => 'Aprende a pensar como una computadora.',
            'xp_reward' => 100, 'order_index' => 0, 'status' => 'published',
        ]);

        foreach (['Variables y Tipos de Datos', 'Estructuras Condicionales'] as $i => $moduleTitle) {
            $module = Module::query()->create([
                'mission_id' => $mission->id,
                'title' => $moduleTitle,
                'required_level' => $i + 1,
                'order_index' => $i,
                'status' => 'published',
            ]);

            foreach (range(1, 4) as $n) {
                Lesson::query()->create([
                    'module_id' => $module->id,
                    'title' => "Lección {$n}: {$moduleTitle}",
                    'content_markdown' => "# {$moduleTitle}\n\nContenido de la lección {$n}. (Markdown editable por el admin.)",
                    'pseudocode_json' => ['ejemplo' => "variable <- valor\nsi condicion entonces\n    imprimir mensaje\nfin_si"],
                    'estimated_minutes' => 10,
                    'xp_reward' => 10,
                    'order_index' => $n - 1,
                    'requires_quiz_pass' => $n === 4,
                    'status' => 'published',
                ]);
            }

            $quiz = Quiz::query()->create([
                'module_id' => $module->id,
                'title' => "Evaluación: {$moduleTitle}",
                'passing_score' => 70,
            ]);

            foreach (range(1, 5) as $n) {
                $q = Question::query()->create([
                    'quiz_id' => $quiz->id,
                    'statement' => "Pregunta {$n} de {$moduleTitle} (¿Qué imprime el pseudocódigo?)",
                    'explanation' => "Explicación de la pregunta {$n}.",
                    'order_index' => $n,
                ]);

                foreach (['Opción A', 'Opción B', 'Opción C'] as $oi => $opt) {
                    QuestionOption::query()->create([
                        'question_id' => $q->id,
                        'option_text' => $opt,
                        'is_correct' => $oi === 0,
                        'feedback' => $oi === 0 ? '¡Correcto! Revisa la lección si lo dudaste.' : 'Incorrecto — revisa la explicación.',
                        'order_index' => $oi,
                    ]);
                }
            }
        }
    }

    /**
     * RB-17: pretest y postest con la MISMA estructura — 14 preguntas (12-15 recomendado),
     * 2 por cada una de las 7 dimensiones del documento.
     */
    private function seedDiagnostics(): void
    {
        $dimensions = [
            'abstraction', 'decomposition', 'pattern_recognition',
            'algorithms', 'variables', 'conditionals', 'loops',
        ];

        foreach (['pre' => 1, 'post' => 1] as $kind => $version) {
            $test = DiagnosticTest::query()->create([
                'kind' => $kind,
                'version' => $version,
                'question_count' => 14,
                'time_limit_minutes' => 15,
                'is_active' => true,
            ]);

            foreach ($dimensions as $i => $dim) {
                foreach ([1, 2] as $j) {
                    DiagnosticQuestion::query()->create([
                        'diagnostic_test_id' => $test->id,
                        'dimension' => $dim,
                        'statement' => "[$kind] Pregunta de {$dim} ".($i * 2 + $j).' — enunciado breve de teoría o mini-ejercicio de pseudocódigo.',
                        'options' => ['Opción A', 'Opción B', 'Opción C'],
                        'correct_index' => 0,
                        'explanation' => "Explicación pedagógica para {$dim}.",
                        'order_index' => $i * 2 + $j,
                    ]);
                }
            }
        }
    }
}
