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

            // Títulos temáticos por lección (crítica de render: 4 títulos idénticos gritan "beta")
            $lessonTitles = $i === 0
                ? ['¿Qué es una variable?', 'Tipos de datos', 'Asignación y operaciones', 'Evaluación del módulo']
                : ['La decisión si-entonces', 'Si-entonces-sino', 'Condicionales anidadas', 'Evaluación del módulo'];

            foreach (range(1, 4) as $n) {
                Lesson::query()->create([
                    'module_id' => $module->id,
                    'title' => $lessonTitles[$n - 1],
                    'content_markdown' => "# {$lessonTitles[$n - 1]}\n\nContenido de la lección {$n} de {$moduleTitle}. (Markdown editable por el admin.)",
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

        // Contenido real por dimensión (crítica de render: placeholders "[pre]/decomposition" confunden)
        $demo = [
            'abstraction' => [
                'statement' => 'Para registrar las notas de tu clase, ¿cuál es el dato MÁS importante que debe guardar el programa?',
                'options' => ['La nota de cada estudiante', 'El color del aula', 'La marca de las computadoras'],
                'correct' => 0,
                'explanation' => 'Abstraer es quedarse solo con lo esencial del problema: las notas.',
            ],
            'decomposition' => [
                'statement' => 'Quieres hacer un programa que calcule tu promedio. ¿Cuál es la mejor forma de dividir el problema?',
                'options' => ['Sumar las notas y dividirlas entre el total', 'Comprar una computadora más rápida', 'Pintar la pantalla de otro color'],
                'correct' => 0,
                'explanation' => 'Descomponer es dividir el problema en pasos pequeños y manejables.',
            ],
            'pattern_recognition' => [
                'statement' => 'Observa la secuencia: 2, 4, 6, 8... ¿qué instrucción repetida la genera?',
                'options' => ['sumar 2 al último número', 'restar 1', 'multiplicar por 10'],
                'correct' => 0,
                'explanation' => 'Reconocer patrones es ver qué se repite y con qué regla.',
            ],
            'algorithms' => [
                'statement' => '¿Cuál es el orden correcto de un algoritmo para cepillarse los dientes?',
                'options' => ['Tomar el cepillo → poner pasta → cepillar → enjuagar', 'Enjuagar → cepillar → tomar el cepillo', 'Cepillar → tomar el cepillo → poner pasta'],
                'correct' => 0,
                'explanation' => 'Un algoritmo es una secuencia ORDENADA de pasos.',
            ],
            'variables' => [
                'statement' => 'En pseudocódigo: edad ← 15, luego edad ← edad + 1. ¿Cuánto vale edad al final?',
                'options' => ['16', '15', 'No se puede saber'],
                'correct' => 0,
                'explanation' => 'La variable se actualiza: 15 + 1 = 16.',
            ],
            'conditionals' => [
                'statement' => 'Si nota >= 60 entonces imprimir "Aprobado" sino imprimir "Reprobado". Con nota = 60, ¿qué imprime?',
                'options' => ['Aprobado', 'Reprobado', 'No imprime nada'],
                'correct' => 0,
                'explanation' => '60 >= 60 es verdadero → entra por la rama "entonces".',
            ],
            'loops' => [
                'statement' => 'Mientras i <= 3: imprimir i, i ← i + 1 (i empieza en 1). ¿Cuántas veces se imprime?',
                'options' => ['3 veces', '4 veces', 'Infinitas veces'],
                'correct' => 0,
                'explanation' => 'Se imprime con i = 1, 2, 3. Cuando i = 4 la condición falla y termina.',
            ],

        ];

        // ---------------- VARIANTE (j=2) — misma dimensión, problema distinto ----------------
        $variante = [
            'abstraction' => [
                'statement' => 'Una app de la biblioteca registra libros. ¿Cuál dato es esencial para saber si un libro está disponible?',
                'options' => ['Si está prestado o no', 'El color de la portada', 'El nombre del editor'],
                'correct' => 0,
                'explanation' => 'Para "disponibilidad" lo esencial es el estado préstamo: sí o no.',
            ],
            'decomposition' => [
                'statement' => 'Un juego necesita saber el puntaje final de un jugador: suma de puntos y restas de penalizaciones. ¿Cómo lo divides?',
                'options' => ['Paso 1: sumar puntos, paso 2: restar penalizaciones', 'Paso 1: dibujar el personaje, paso 2: sonar la música', 'Buscar el promedio y no pensarlo más'],
                'correct' => 0,
                'explanation' => 'Dos pasos separados: primero puntos, luego penalizaciones.',
            ],
            'pattern_recognition' => [
                'statement' => 'Observa: 100, 90, 80, 70... ¿qué instrucción repetida genera la lista?',
                'options' => ['restar 10 al último número', 'sumar 5', 'dividir entre 2'],
                'correct' => 0,
                'explanation' => 'El patrón es restar 10 en cada paso.',
            ],
            'algorithms' => [
                'statement' => 'Para hacer un sándwich, ¿qué secuencia de pasos es correcta?',
                'options' => ['Sacar pan → untar mantequilla → poner queso → tapar con otro pan', 'Tapar con otro pan → poner queso → sacar pan', 'Poner queso → sacar pan → untar mantequilla'],
                'correct' => 0,
                'explanation' => 'Un algoritmo exige que el orden produzca el resultado.',
            ],
            'variables' => [
                'statement' => 'En pseudocódigo: saldo ← 50, saldo ← saldo - 20, saldo ← saldo * 2. ¿Cuánto vale saldo?',
                'options' => ['60', '30', '20'],
                'correct' => 0,
                'explanation' => '50 - 20 = 30, luego 30 × 2 = 60.',
            ],
            'conditionals' => [
                'statement' => 'Si edad >= 18 entonces imprimir "Adulto" sino imprimir "Menor". Con edad = 17, ¿qué imprime?',
                'options' => ['Menor', 'Adulto', 'Error'],
                'correct' => 0,
                'explanation' => '17 >= 18 es falso → va por el "sino".',
            ],
            'loops' => [
                'statement' => 'Repetir 4 veces: imprimir "EduGT". ¿Cuántas líneas se muestran?',
                'options' => ['4', '3', '5'],
                'correct' => 0,
                'explanation' => 'El bucle fijo ejecuta el cuerpo exactamente 4 veces.',
            ],
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
                // j=1 pregunta base, j=2 variante distinta (misma dimensión, problema equivalente)
                // RB-17: pre y post comparten estructura; el postest reordena opciones (anti-memoria)
                $base = [$demo[$dim], $variante[$dim]];

                foreach ([1, 2] as $j) {
                    $d = $base[$j - 1];
                    $isPost = ($kind === 'post');

                    // Rotación DETERMINISTA de la posición correcta: no todas son "A"
                    // patrón por pregunta global: A, B, C, A, C, B... (i*2+j) manda
                    $rotaciones = [0, 1, 2, 0, 2, 1, 0, 1, 2, 0, 2, 1, 0, 1]; // 14 preguntas
                    $indiceGlobal = $i * 2 + $j - 1;                          // 0..13
                    $posCorrecta = $rotaciones[$indiceGlobal % count($rotaciones)];

                    // Opciones finales: correcta en la posición rotada, distractoras en el resto
                    $correcta = $d['options'][$d['correct']];
                    $distractoras = array_values(array_diff($d['options'], [$correcta]));
                    $final = [];
                    $di = 0;
                    for ($pos = 0; $pos < 3; $pos++) {
                        $final[$pos] = ($pos === $posCorrecta) ? $correcta : $distractoras[$di++];
                    }

                    DiagnosticQuestion::query()->create([
                        'diagnostic_test_id' => $test->id,
                        'dimension' => $dim,
                        'statement' => $isPost && $j === 1
                            ? $d['statement'].' (versión postest)'
                            : $d['statement'],
                        'options' => $final,
                        'correct_index' => $posCorrecta,
                        'explanation' => $d['explanation'],
                        'order_index' => $i * 2 + $j,
                    ]);
                }
            }
        }
    }
}
