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
            'slug' => 'mision-1-fundamentos',
            'title' => 'Misión 1: Fundamentos de Lógica',
            'description' => 'Aprende a pensar como una computadora y domina las bases de la programación.',
            'xp_reward' => 100,
            'order_index' => 0,
            'status' => 'published',
        ]);

        $curriculumData = [
            [
                'module_title' => 'Variables y Tipos de Datos',
                'lessons' => [
                    [
                        'title' => '¿Qué es una variable?',
                        'markdown' => "## 📦 ¿Qué es una Variable?\n\nImagina que una **variable** es como una caja con una etiqueta donde puedes guardar un dato para usarlo más adelante en tu programa.\n\nEl nombre de la etiqueta es el **identificador** (por ejemplo: `puntos`, `edad`, `nombre_estudiante`) y lo que colocas adentro es el **valor** (por ejemplo: `100`, `16`, `\"Carlos\"`).\n\n---\n\n### 🔍 Las 3 etapas de una variable:\n1. **Declaración:** Le dices a la computadora cómo se llamará tu variable.\n2. **Asignación (`=`):** Guardas un valor inicial dentro de la variable.\n3. **Lectura / Modificación:** Usas su valor para hacer cálculos o lo cambias por uno nuevo.\n\n> 💡 **Regla de oro:** En programación usamos el signo `=` para **asignar** (guardar) un valor. Cada vez que asignas un nuevo valor a una variable, el anterior se sobreescribe y se pierde. ¡La variable solo puede almacenar un dato a la vez!",
                        'pseudocode' => [
                            'codigo' => "// 1. Guardar valores iniciales (Asignación)\nedad = 16\npuntos = 50\n\n// 2. Modificar el valor acumulando puntos\npuntos = puntos + 25\n\n// 3. Mostrar el resultado en pantalla\nEscribir \"Edad del estudiante:\", edad\nEscribir \"Puntaje final:\", puntos",
                            'explicacion' => "Al inicio 'puntos' vale 50. Luego calculamos 50 + 25 = 75 y lo guardamos nuevamente en 'puntos'.",
                            'reto' => [
                                'pregunta' => "Si ejecutamos el siguiente código: `puntos = 10`, luego `puntos = 20`, y después `puntos = puntos + 5`. ¿Qué valor almacenará la variable 'puntos' al final?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '35', 'es_correcta' => false, 'feedback' => 'Incorrecto: recuerda que el valor inicial 10 fue sobreescrito por el 20.'],
                                    ['id' => 'b', 'texto' => '25', 'es_correcta' => true, 'feedback' => '¡Excelente deducción! El 10 inicial se reemplazó por 20, y luego 20 + 5 = 25.'],
                                    ['id' => 'c', 'texto' => '5', 'es_correcta' => false, 'feedback' => 'Incorrecto: el 5 se suma al valor anterior (20), no lo reemplaza.']
                                ]
                            ]
                        ],
                        'minutes' => 8,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Tipos de datos',
                        'markdown' => "## 🏷️ Clasificación de Tipos de Datos\n\nLas computadoras necesitan saber **qué clase de información** están procesando para no cometer errores matemáticos o de memoria.\n\n---\n\n### 📌 Los 4 Tipos de Datos Fundamentales:\n\n1. **Entero (`Entero`):** Números sin decimales, positivos o negativos (`10`, `-3`, `2026`).\n2. **Real / Decimal (`Real`):** Números con parte decimal (`3.1416`, `85.50`, `1.75`).\n3. **Cadena de Texto (`Cadena`):** Palabras o frases entre comillas dobles (`\"EduGT\"`, `\"Jutiapa\"`).\n4. **Lógico / Booleano (`Lógico`):** Solo dos posibles estados: `Verdadero` o `Falso`.\n\n> ⚠️ **Diferencia clave:** El número `10` es un Entero matemático. Pero `\"10\"` (con comillas) es un Texto y no se puede sumar directamente.",
                        'pseudocode' => [
                            'codigo' => "// Declaración y asignación de diferentes tipos de datos\nnombre_usuario = \"Elena\"\nedad_usuario = 17\npromedio_academico = 89.75\ncuenta_activa = Verdadero\n\nEscribir \"Usuario registrado:\", nombre_usuario\nEscribir \"Edad:\", edad_usuario\nEscribir \"Promedio final:\", promedio_academico\nEscribir \"¿Estado activo?:\", cuenta_activa",
                            'explicacion' => "Cada variable almacena un tipo específico: texto, número entero, número decimal y booleano.",
                            'reto' => [
                                'pregunta' => "Tienes las siguientes variables: `precio = 45.50`, `cantidad = 3`, `activo = Falso`, `nombre = \"Juan\"`. ¿A qué tipo de dato fundamental corresponde la variable `activo`?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Cadena de texto', 'es_correcta' => false, 'feedback' => 'Incorrecto: no está encerrada entre comillas dobles.'],
                                    ['id' => 'b', 'texto' => 'Lógico / Booleano', 'es_correcta' => true, 'feedback' => '¡Correcto! Los valores lógicos solo pueden ser Verdadero o Falso.'],
                                    ['id' => 'c', 'texto' => 'Entero', 'es_correcta' => false, 'feedback' => 'Incorrecto: no representa una cantidad numérica.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Asignación y operaciones',
                        'markdown' => "## ⚡ Asignación y Operadores Aritméticos\n\nEl operador de asignación (`=`) evalúa todo lo que está a la **derecha** y guarda el resultado final en la variable de la **izquierda**.\n\n---\n\n### 🧮 Operadores Aritméticos Básicos:\n* **Suma (`+`):** `total = precio + iva`\n* **Resta (`-`):** `vidas = vidas - 1`\n* **Multiplicación (`*`):** `area = base * altura`\n* **División (`/`):** `promedio = suma / 3`\n* **Módulo (`%` o `MOD`):** Devuelve el residuo de una división. Ejemplo: `10 % 3` es `1`.\n\n---\n\n### 🎯 Jerarquía de Operaciones:\n1. Paréntesis `()`\n2. Multiplicación `*` y División `/`\n3. Suma `+` y Resta `-`\n\n> 📌 **Regla fundamental:** Un solo signo `=` sirve para **guardar datos**, mientras que el doble signo `==` sirve para **comparar**.",
                        'pseudocode' => [
                            'codigo' => "nota1 = 85\nnota2 = 95\nnota3 = 70\n\n// Los paréntesis aseguran el cálculo correcto del promedio\npromedio = (nota1 + nota2 + nota3) / 3\n\n// Incrementar bonificación de 5 puntos\npromedio = promedio + 5\n\nEscribir \"Promedio general con bono:\", promedio",
                            'explicacion' => "Primero se suman las notas entre paréntesis (250), se divide entre 3 (83.33) y luego se agregan 5 puntos de bono (88.33).",
                            'reto' => [
                                'pregunta' => "Analiza la siguiente operación en pseudocódigo: `resultado = 10 + 5 * 2`. ¿Cuál será el valor final almacenado en `resultado`?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '30', 'es_correcta' => false, 'feedback' => 'Incorrecto: recuerda que la multiplicación tiene mayor prioridad que la suma.'],
                                    ['id' => 'b', 'texto' => '20', 'es_correcta' => true, 'feedback' => '¡Correcto! Por jerarquía matemática, primero se resuelve 5 * 2 = 10 y luego 10 + 10 = 20.'],
                                    ['id' => 'c', 'texto' => '25', 'es_correcta' => false, 'feedback' => 'Incorrecto: revisa la jerarquía de las operaciones aritméticas.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Evaluación del módulo',
                        'markdown' => "## 🏆 Hito del Módulo: Variables y Datos\n\n¡Has completado todas las lecciones sobre variables, tipos y operadores!\n\n---\n\n### 📋 Repaso de conceptos clave:\n* Almacenar datos en memoria con nombres descriptivos usando `=`. \n* Elegir el tipo de dato adecuado (Entero, Real, Cadena o Lógico).\n* Respetar la precedencia matemática con paréntesis `()`.\n\n> 🎯 **Siguiente Reto:** Presenta la evaluación del módulo. Con un **70% o más** desbloquearás la insignia **Novato en Lógica** y el Módulo 2.",
                        'pseudocode' => [
                            'codigo' => "// Desafío integrador de variables\nx = 10\ny = 20\nx = x + y\ny = x - y\nx = x - y\n\nEscribir \"Valor final de x:\", x  // Imprime 20\nEscribir \"Valor final de y:\", y  // Imprime 10",
                            'explicacion' => "Intercambio clásico de dos variables utilizando sumas y restas sin variables auxiliares.",
                            'reto' => [
                                'pregunta' => "Acertijo de intercambio: Si `a = 5`, `b = 10`, `aux = a`, `a = b`, `b = aux`. ¿Qué valores tienen las variables `a` y `b` al finalizar?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'a = 10 y b = 5', 'es_correcta' => true, 'feedback' => '¡Brillante! Es el algoritmo clásico de intercambio (swap) utilizando una variable auxiliar.'],
                                    ['id' => 'b', 'texto' => 'a = 10 y b = 10', 'es_correcta' => false, 'feedback' => 'Incorrecto: el valor original de `a` se resguardó en `aux` antes de sobreescribirse.'],
                                    ['id' => 'c', 'texto' => 'a = 5 y b = 10', 'es_correcta' => false, 'feedback' => 'Incorrecto: observa con cuidado cómo se transfieren los valores paso a paso.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => true,
                    ]
                ],
                'quiz_questions' => [
                    [
                        'statement' => "Analiza el siguiente algoritmo en pseudocódigo:\n\n```\na = 15\nb = 10\na = a + 5\nresultado = a - b\nEscribir resultado\n```\n\n¿Qué valor numérico se imprimirá en pantalla al ejecutar el algoritmo?",
                        'explanation' => "Primero la variable `a` se actualiza: 15 + 5 = 20. Luego se calcula `resultado = 20 - 10 = 10`.",
                        'options' => [
                            ['texto' => '10', 'correcta' => true, 'feedback' => '¡Correcto! `a` pasa de 15 a 20, luego `resultado = 20 - 10 = 10`.'],
                            ['texto' => '5', 'correcta' => false, 'feedback' => 'Incorrecto: recuerda que a la variable `a` se le sumaron 5 unidades antes de la resta.'],
                            ['texto' => '20', 'correcta' => false, 'feedback' => 'Incorrecto: 20 es el valor intermedio de `a`, no el valor de `resultado`.']
                        ]
                    ],
                    [
                        'statement' => "Un sistema escolar necesita registrar si un estudiante ya pagó su matrícula escolar. ¿Cuál es el **tipo de dato más apropiado** para almacenar esta información?",
                        'explanation' => "Los datos lógicos o booleanos (`Verdadero` / `Falso`) son los idóneos para representar estados binarios como sí/no o activo/inactivo.",
                        'options' => [
                            ['texto' => 'Lógico / Booleano (Verdadero o Falso)', 'correcta' => true, 'feedback' => '¡Correcto! Es perfecto para banderas binarias de sí/no o estado pagado/no pagado.'],
                            ['texto' => 'Cadena de texto ("Sí" o "No")', 'correcta' => false, 'feedback' => 'Incorrecto: aunque es posible, una cadena no es el tipo óptimo ni estándar para estados booleanos.'],
                            ['texto' => 'Entero (1 o 0)', 'correcta' => false, 'feedback' => 'Incorrecto: los tipos lógicos expresan formalmente valores de verdad en lógica computacional.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el resultado de evaluar la siguiente expresión aritmética respetando la **jerarquía de operadores**?\n\n```\nvalor = 20 - 4 * 3 + 2\n```",
                        'explanation' => "Por jerarquía de operadores: primero se resuelve `4 * 3 = 12`. La expresión queda `20 - 12 + 2`. Luego de izquierda a derecha: `20 - 12 = 8` y `8 + 2 = 10`.",
                        'options' => [
                            ['texto' => '10', 'correcta' => true, 'feedback' => '¡Correcto! Primero la multiplicación `4 * 3 = 12`, luego de izquierda a derecha `20 - 12 = 8` y `8 + 2 = 10`.'],
                            ['texto' => '50', 'correcta' => false, 'feedback' => 'Incorrecto: recuerda que la multiplicación tiene prioridad sobre la resta y la suma.'],
                            ['texto' => '0', 'correcta' => false, 'feedback' => 'Incorrecto: no se calcula de izquierda a derecha sin respetar la precedencia matemática.']
                        ]
                    ],
                    [
                        'statement' => "Observa el siguiente intercambio de valores:\n\n```\nx = 100\ny = 200\naux = x\nx = y\ny = aux\n```\n\n¿Cuáles son los valores finales de `x` y de `y` respectivamente?",
                        'explanation' => "La variable `aux` almacena temporalmente el 100 de `x`. `x` toma el 200 de `y`, y finalmente `y` toma el 100 guardado en `aux`.",
                        'options' => [
                            ['texto' => 'x = 200, y = 100', 'correcta' => true, 'feedback' => '¡Correcto! Es el algoritmo canónico de intercambio (swap) que invierte los valores de ambas variables.'],
                            ['texto' => 'x = 100, y = 200', 'correcta' => false, 'feedback' => 'Incorrecto: los valores cambiaron exitosamente a través de la variable auxiliar `aux`.'],
                            ['texto' => 'x = 200, y = 200', 'correcta' => false, 'feedback' => 'Incorrecto: el valor original de `x` fue preservado en `aux` antes de sobreescribir `x`.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál de las siguientes asignaciones causaría un **error de incompatibilidad de tipos** en un lenguaje de programación?",
                        'explanation' => "No es posible realizar una suma matemática entre una cadena de texto y un número entero.",
                        'options' => [
                            ['texto' => 'edad = "dieciséis" + 5', 'correcta' => true, 'feedback' => '¡Correcto! No se puede sumar aritméticamente un número entero a una cadena de texto.'],
                            ['texto' => 'promedio = 85.5 + 10', 'correcta' => false, 'feedback' => 'Incorrecto: sumar un número decimal (Real) con un Entero es una operación aritmética válida.'],
                            ['texto' => 'es_mayor = Verdadero', 'correcta' => false, 'feedback' => 'Incorrecto: asignar un valor booleano a una variable es totalmente válido.']
                        ]
                    ]
                ]
            ],
            [
                'module_title' => 'Estructuras Condicionales',
                'lessons' => [
                    [
                        'title' => 'La decisión si-entonces',
                        'markdown' => "## 🔀 La Decisión Si-Entonces (Condicional Simple)\n\nEn la vida real tomamos decisiones constantemente: *\"Si está lloviendo, entonces llevo paraguas\"*. En programación, las computadoras hacen exactamente lo mismo.\n\nUna estructura condicional evalúa una **expresión lógica**:\n* Si la condición es **Verdadera**, ejecuta las instrucciones dentro del bloque.\n* Si es **Falsa**, simplemente se salta ese bloque y continúa.\n\n---\n\n### ⚖️ Operadores de Comparación:\n* `==` : Igual a (compara si dos valores son iguales)\n* `!=` o `<>` : Diferente de\n* `>` : Mayor que | `<` : Menor que\n* `>=` : Mayor o igual | `<=` : Menor o igual",
                        'pseudocode' => [
                            'codigo' => "puntos = 120\n\nSi puntos >= 100 Entonces\n    Escribir \"¡Felicitaciones! Has subido de nivel.\"\n    Escribir \"Obtienes una bonificación de +20 XP.\"\nFinSi\n\nEscribir \"Continuando la aventura...\"",
                            'explicacion' => "Como 120 >= 100 es Verdadero, el bloque interior se ejecuta y muestra las felicitaciones.",
                            'reto' => [
                                'pregunta' => "Si tenemos `temperatura = 32` y el código `Si temperatura > 30 Entonces Escribir \"¡Hace calor!\" FinSi`. ¿Qué se mostrará en pantalla?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '¡Hace calor!', 'es_correcta' => true, 'feedback' => '¡Correcto! Como 32 > 30 es Verdadero, el bloque interior se ejecuta.'],
                                    ['id' => 'b', 'texto' => 'Nada (la pantalla queda vacía)', 'es_correcta' => false, 'feedback' => 'Incorrecto: la condición sí se cumple, por lo que la instrucción sí se ejecuta.'],
                                    ['id' => 'c', 'texto' => 'Error de sintaxis', 'es_correcta' => false, 'feedback' => 'Incorrecto: la estructura condicional simple está escrita correctamente.']
                                ]
                            ]
                        ],
                        'minutes' => 8,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Si-entonces-sino',
                        'markdown' => "## ⚖️ Si-Entonces-Sino (Alternativa Doble)\n\n¿Qué ocurre cuando quieres ejecutar una acción si la condición se cumple, pero **otra acción totalmente distinta** si no se cumple?\n\nPara esto usamos la cláusula **Sino** (`Else`).\n\n---\n\n### 📌 Estructura Fundamental:\n```\nSi condicion Entonces\n    // Se ejecuta solo si es VERDADERO\nSino\n    // Se ejecuta solo si es FALSO\nFinSi\n```\n\n> 💡 **Regla:** En una estructura `Si-Entonces-Sino`, **solo una de las dos ramas** se ejecutará en cada ejecución, nunca ambas.",
                        'pseudocode' => [
                            'codigo' => "calificacion = 58\n\nSi calificacion >= 60 Entonces\n    Escribir \"Estado: Aprobado\"\n    Escribir \"¡Excelente trabajo!\"\nSino\n    Escribir \"Estado: Reprobado\"\n    puntos_faltantes = 60 - calificacion\n    Escribir \"Te faltaron\", puntos_faltantes, \"puntos para aprobar.\"\nFinSi",
                            'explicacion' => "Como 58 >= 60 es Falso, el programa se salta la rama 'Entonces' y ejecuta únicamente el 'Sino'.",
                            'reto' => [
                                'pregunta' => "Dado el código: `edad = 15`. `Si edad >= 18 Entonces Escribir \"Mayor\" Sino Escribir \"Menor\" FinSi`. ¿Qué rama se ejecutará?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Ambas ramas de forma consecutiva', 'es_correcta' => false, 'feedback' => 'Incorrecto: las ramas Entonces y Sino son mutuamente excluyentes; solo una se ejecuta.'],
                                    ['id' => 'b', 'texto' => 'La rama Sino, mostrando \"Menor\"', 'es_correcta' => true, 'feedback' => '¡Exacto! Como 15 >= 18 es Falso, el flujo salta directamente al bloque Sino.'],
                                    ['id' => 'c', 'texto' => 'La rama Entonces, mostrando \"Mayor\"', 'es_correcta' => false, 'feedback' => 'Incorrecto: 15 no cumple la condición de ser mayor o igual a 18.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Condicionales anidadas',
                        'markdown' => "## 🧩 Condicionales Anidadas y Operadores Lógicos\n\nA menudo, una sola pregunta no es suficiente para tomar una decisión compleja. Podemos combinar múltiples condiciones usando **operadores lógicos**:\n\n---\n\n### 🧠 Operadores Lógicos:\n* **`Y` (`AND`):** Todas las condiciones deben cumplirse.\n* **`O` (`OR`):** Basta con que al menos UNA condición se cumpla.\n* **`NO` (`NOT`):** Invierte el valor lógico.\n\n---\n\n### 📂 Condicionales Anidadas (`Si-SinoSi`):\nPermiten clasificar datos en múltiples categorías en cascada (ejemplo: escalas de notas A, B, C, D).",
                        'pseudocode' => [
                            'codigo' => "nota = 88\n\nSi nota >= 90 Entonces\n    Escribir \"Rango: Excelente (A)\"\nSino\n    Si nota >= 80 Y nota < 90 Entonces\n        Escribir \"Rango: Muy Bueno (B)\"\n    Sino\n        Si nota >= 60 Entonces\n            Escribir \"Rango: Aprobado (C)\"\n        Sino\n            Escribir \"Rango: Necesita Refuerzo (D)\"\n        FinSi\n    FinSi\nFinSi",
                            'explicacion' => "Con nota = 88, la primera condición es falsa, pero la segunda (nota >= 80 Y nota < 90) es verdadera, mostrando 'Muy Bueno (B)'.",
                            'reto' => [
                                'pregunta' => "Evalúa la siguiente expresión lógica combinada: `(5 > 3) Y (10 < 8)`. ¿Cuál es el resultado booleano final?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Verdadero', 'es_correcta' => false, 'feedback' => 'Incorrecto: el operador Y exige que AMBAS condiciones se cumplan, pero 10 < 8 es Falso.'],
                                    ['id' => 'b', 'texto' => 'Falso', 'es_correcta' => true, 'feedback' => '¡Muy bien! (5 > 3) es Verdadero, pero (10 < 8) es Falso. Verdadero Y Falso da Falso.'],
                                    ['id' => 'c', 'texto' => 'Indeterminado', 'es_correcta' => false, 'feedback' => 'Incorrecto: toda expresión booleana tiene un valor definido de Verdadero o Falso.']
                                ]
                            ]
                        ],
                        'minutes' => 12,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Evaluación del módulo',
                        'markdown' => "## 🏆 Hito del Módulo: Estructuras Condicionales\n\n¡Has aprendido a controlar el flujo de ejecución de tus programas!\n\n---\n\n### 📋 Repaso de conceptos clave:\n* Estructuras `Si-Entonces` para decisiones simples.\n* Alternativas dobles con `Si-Entonces-Sino`.\n* Combinación de condiciones con operadores `Y`, `O`, `NO`.\n\n> 🎯 **Siguiente Reto:** Presenta la evaluación final de condicionales (mínimo 70%) para desbloquear la insignia **Mago del Control**.",
                        'pseudocode' => [
                            'codigo' => "hora = 14\nes_dia_laboral = Verdadero\n\nSi es_dia_laboral Y (hora >= 8 Y hora <= 17) Entonces\n    Escribir \"Horario de atención disponible.\"\nSino\n    Escribir \"Oficina cerrada fuera de horario.\"\nFinSi",
                            'explicacion' => "Verificación de rangos horarios con operadores lógicos combinados.",
                            'reto' => [
                                'pregunta' => "En un sistema de acceso: `tiene_pase = Falso`, `es_admin = Verdadero`. La condición es `Si tiene_pase O es_admin Entonces Escribir \"Acceso Permitido\" FinSi`. ¿Qué ocurrirá?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Mostrará \"Acceso Permitido\", porque con el operador O basta con que una condición sea Verdadera.', 'es_correcta' => true, 'feedback' => '¡Excelente! Al ser `es_admin` Verdadero, la condición completa del operador O se cumple.'],
                                    ['id' => 'b', 'texto' => 'No mostrará nada, porque `tiene_pase` es Falso.', 'es_correcta' => false, 'feedback' => 'Incorrecto: el operador O no requiere que ambas sean verdaderas, basta con una.'],
                                    ['id' => 'c', 'texto' => 'Dará un error por tener dos variables con estados distintos.', 'es_correcta' => false, 'feedback' => 'Incorrecto: combinar variables booleanas con operadores lógicos es una práctica estándar y válida.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => true,
                    ]
                ],
                'quiz_questions' => [
                    [
                        'statement' => "Analiza el siguiente fragmento de código condicional:\n\n```\npuntos = 85\nSi puntos >= 90 Entonces\n    mensaje = \"Nivel Oro\"\nSino\n    Si puntos >= 75 Entonces\n        mensaje = \"Nivel Plata\"\n    Sino\n        mensaje = \"Nivel Bronce\"\n    FinSi\nFinSi\nEscribir mensaje\n```\n\n¿Qué texto mostrará la pantalla?",
                        'explanation' => "Como `85 >= 90` es Falso, pasa al bloque `Sino`. Allí `85 >= 75` es Verdadero, asignando `\"Nivel Plata\"`.",
                        'options' => [
                            ['texto' => 'Nivel Plata', 'correcta' => true, 'feedback' => '¡Correcto! 85 no es >= 90, pero sí cumple la segunda condición `85 >= 75`.'],
                            ['texto' => 'Nivel Oro', 'correcta' => false, 'feedback' => 'Incorrecto: 85 no es mayor o igual a 90.'],
                            ['texto' => 'Nivel Bronce', 'correcta' => false, 'feedback' => 'Incorrecto: el flujo entra a la rama `Nivel Plata` antes de llegar a Bronce.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el valor booleano resultante de evaluar la siguiente expresión lógica combinada?\n\n```\nresultado = (10 > 5) Y (3 == 4)\n```",
                        'explanation' => "La primera condición `(10 > 5)` es Verdadera, pero la segunda `(3 == 4)` es Falsa. En una conjunción (`Y`), `Verdadero Y Falso` produce `Falso`.",
                        'options' => [
                            ['texto' => 'Falso', 'correcta' => true, 'feedback' => '¡Correcto! `10 > 5` es Verdadero, pero `3 == 4` es Falso. Con el operador `Y`, ambas deben ser verdaderas.'],
                            ['texto' => 'Verdadero', 'correcta' => false, 'feedback' => 'Incorrecto: con el operador `Y`, si una sola condición es falsa, toda la expresión es falsa.'],
                            ['texto' => 'Error de sintaxis', 'correcta' => false, 'feedback' => 'Incorrecto: la expresión lógica de comparación y conjunción está escrita correctamente.']
                        ]
                    ],
                    [
                        'statement' => "Para ingresar a una atracción mecánica se requiere que el visitante tenga **al menos 12 años** O que **venga acompañado por un tutor adulto**. Si `edad = 10` y `acompanado = Verdadero`, ¿qué expresión lógica evalúa correctamente esta regla?",
                        'explanation' => "La regla utiliza una disyunción (`O`): basta con tener 12 o más años, O bien venir acompañado por un tutor.",
                        'options' => [
                            ['texto' => 'edad >= 12 O acompanado == Verdadero', 'correcta' => true, 'feedback' => '¡Correcto! El operador `O` permite el ingreso si se cumple cualquiera de las dos condiciones.'],
                            ['texto' => 'edad >= 12 Y acompanado == Verdadero', 'correcta' => false, 'feedback' => 'Incorrecto: el operador `Y` obligaría a tener 12 años y además venir acompañado.'],
                            ['texto' => 'edad == 12 O acompanado == Falso', 'correcta' => false, 'feedback' => 'Incorrecto: no representa fielmente las condiciones de la regla.']
                        ]
                    ],
                    [
                        'statement' => "Observa el siguiente algoritmo de cajero automático:\n\n```\nsaldo = 50\nmonto_retiro = 80\n\nSi monto_retiro <= saldo Entonces\n    saldo = saldo - monto_retiro\n    Escribir \"Retiro exitoso\"\nSino\n    Escribir \"Fondos insuficientes\"\nFinSi\nEscribir \"Saldo actual:\", saldo\n```\n\n¿Qué dos líneas de salida se imprimirán en pantalla?",
                        'explanation' => "`80 <= 50` es Falso, por lo que entra al bloque `Sino` imprimiendo 'Fondos insuficientes' y el `saldo` se mantiene intacto en 50.",
                        'options' => [
                            ['texto' => '"Fondos insuficientes" y "Saldo actual: 50"', 'correcta' => true, 'feedback' => '¡Correcto! 80 <= 50 es Falso, se ejecuta el Sino y el saldo no se altera.'],
                            ['texto' => '"Retiro exitoso" y "Saldo actual: -30"', 'correcta' => false, 'feedback' => 'Incorrecto: la condición condicional previene que el saldo quede en negativo.'],
                            ['texto' => '"Fondos insuficientes" y "Saldo actual: 0"', 'correcta' => false, 'feedback' => 'Incorrecto: el saldo no se redujo a cero porque no se ejecutó el retiro.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el efecto del operador lógico `NO` (`NOT`) al aplicarse sobre una variable o expresión booleana?",
                        'explanation' => "El operador `NO` niega o invierte el valor de verdad: convierte `Verdadero` en `Falso`, y `Falso` en `Verdadero`.",
                        'options' => [
                            ['texto' => 'Invierte el valor de verdad (Verdadero pasa a Falso y viceversa)', 'correcta' => true, 'feedback' => '¡Correcto! Es la operación de negación lógica unaria.'],
                            ['texto' => 'Compara si dos números son exactamente iguales', 'correcta' => false, 'feedback' => 'Incorrecto: esa es la función del operador de comparación `==`.'],
                            ['texto' => 'Borra la variable de la memoria de la computadora', 'correcta' => false, 'feedback' => 'Incorrecto: `NO` es un operador lógico, no una instrucción de eliminación.']
                        ]
                    ]
                ]
            ]
        ];

        foreach ($curriculumData as $i => $modData) {
            $module = Module::query()->create([
                'mission_id' => $mission->id,
                'title' => $modData['module_title'],
                'required_level' => $i + 1,
                'order_index' => $i,
                'status' => 'published',
            ]);

            foreach ($modData['lessons'] as $n => $lData) {
                Lesson::query()->create([
                    'module_id' => $module->id,
                    'title' => $lData['title'],
                    'content_markdown' => $lData['markdown'],
                    'pseudocode_json' => $lData['pseudocode'],
                    'estimated_minutes' => $lData['minutes'],
                    'xp_reward' => $lData['xp'],
                    'order_index' => $n,
                    'requires_quiz_pass' => $lData['requires_quiz'],
                    'status' => 'published',
                ]);
            }

            $quiz = Quiz::query()->create([
                'module_id' => $module->id,
                'title' => "Evaluación: {$modData['module_title']}",
                'passing_score' => 70,
            ]);

            foreach ($modData['quiz_questions'] as $qn => $qData) {
                $q = Question::query()->create([
                    'quiz_id' => $quiz->id,
                    'statement' => $qData['statement'],
                    'explanation' => $qData['explanation'],
                    'order_index' => $qn + 1,
                ]);

                foreach ($qData['options'] as $oi => $opt) {
                    QuestionOption::query()->create([
                        'question_id' => $q->id,
                        'option_text' => $opt['texto'],
                        'is_correct' => $opt['correcta'],
                        'feedback' => $opt['feedback'],
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
                'statement' => 'En pseudocódigo: edad = 15, luego edad = edad + 1. ¿Cuánto vale edad al final?',
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
                'statement' => 'Mientras i <= 3: imprimir i, i = i + 1 (i empieza en 1). ¿Cuántas veces se imprime?',
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
                'statement' => 'En pseudocódigo: saldo = 50, saldo = saldo - 20, saldo = saldo * 2. ¿Cuánto vale saldo?',
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
