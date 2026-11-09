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
use App\Domain\Lab\Models\LabChallenge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
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
        $this->seedLabChallenges();
    }

    private function seedBadges(): void
    {
        $badges = [
            ['slug' => 'novato-logica', 'title' => 'Novato en Lógica', 'description' => 'Aprobaste tu primer cuestionario.', 'event' => 'quiz_passed', 'conditions' => ['count' => 1]],
            ['slug' => 'maestro-pseudocodigo', 'title' => 'Maestro del Pseudocódigo', 'description' => 'Aprobaste los 3 cuestionarios de la ruta curricular.', 'event' => 'quiz_passed', 'conditions' => ['count' => 3]],
            ['slug' => 'explorador-variables', 'title' => 'Explorador de Variables', 'description' => 'Completaste el módulo de variables.', 'event' => 'lesson_completed', 'conditions' => ['count' => 4]],
            ['slug' => 'mago-del-control', 'title' => 'Mago del Control', 'description' => 'Dominaste las estructuras condicionales.', 'event' => 'quiz_passed', 'conditions' => ['count' => 2]],
            ['slug' => 'maestro-del-bucle', 'title' => 'Maestro del Bucle', 'description' => 'Dominaste las estructuras cíclicas.', 'event' => 'quiz_passed', 'conditions' => ['count' => 3]],
            ['slug' => 'depurador-elite', 'title' => 'Depurador Élite', 'description' => 'Aprobaste un cuestionario en el primer intento.', 'event' => 'quiz_passed', 'conditions' => ['count' => 1, 'attempt_number' => 1]],
            ['slug' => 'arquitecto-algoritmos', 'title' => 'Arquitecto de Algoritmos', 'description' => 'Superaste al menos 3 prácticas en el Laboratorio de Pseudocódigo y Prueba de Escritorio.', 'event' => 'lab_completed', 'conditions' => ['count' => 3]],
        ];

        foreach ($badges as $b) {
            $badge = Badge::query()->updateOrCreate(
                ['slug' => $b['slug']],
                ['title' => $b['title'], 'description' => $b['description']]
            );
            BadgeRule::query()->updateOrCreate(
                ['badge_id' => $badge->id, 'event' => $b['event']],
                ['conditions' => $b['conditions'], 'is_active' => true]
            );
        }
    }

    private function seedCurriculum(): void
    {
        $mission = Mission::query()->firstOrCreate(
            ['slug' => 'mision-1-fundamentos'],
            [
                'title' => 'Misión 1: Fundamentos de Lógica',
                'description' => 'Aprende a pensar como una computadora y domina las bases de la programación.',
                'xp_reward' => 100,
                'order_index' => 0,
                'status' => 'published',
            ]
        );

        $curriculumData = [
            [
                'module_title' => 'Variables y Tipos de Datos',
                'lessons' => [
                    [
                        'title' => '¿Qué es una variable?',
                        'markdown' => "## 📦 ¿Qué es una Variable?\n\nImagina que una **variable** es como una caja con una etiqueta donde puedes guardar un dato para usarlo más adelante en tu programa.\n\nEl nombre de la etiqueta es el **identificador** (por ejemplo: `puntos`, `edad`, `nombre_estudiante`) y lo que colocas adentro es el **valor** (por ejemplo: `100`, `16`, `\"Carlos\"`).\n\n---\n\n### 🔍 Las 3 etapas de una variable:\n1. **Declaración:** Le dices a la computadora cómo se llamará tu variable.\n2. **Asignación (`=`):** Guardas un valor inicial dentro de la variable.\n3. **Lectura / Modificación:** Usas su valor para hacer cálculos o lo cambias por uno nuevo.\n\n> 💡 **Regla de oro:** En programación usamos el signo `=` para **asignar** (guardar) un valor. Cada vez que asignas un nuevo valor a una variable, el anterior se sobreescribe y se pierde. ¡La variable solo puede almacenar un dato a la vez!",
                        'pseudocode' => [
                            'codigo' => "// 1. Guardar valores iniciales (Asignación)\nedad = 16\npuntos = 50\n\n// 2. Modificar el valor acumulando puntos\npuntos = puntos + 25\n\n// 3. Mostrar el resultado en pantalla\nprint(\"Edad del estudiante:\", edad)\nprint(\"Puntaje final:\", puntos)",
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
                        'markdown' => "## 🏷️ Clasificación de Tipos de Datos\n\nLas computadoras necesitan saber **qué clase de información** están procesando para no cometer errores matemáticos o de memoria.\n\n---\n\n### 📌 Los 4 Tipos de Datos Fundamentales:\n\n1. **Entero (`int`):** Números sin decimales, positivos o negativos (`10`, `-3`, `2026`).\n2. **Real / Decimal (`float`):** Números con parte decimal (`3.1416`, `85.50`, `1.75`).\n3. **Cadena de Texto (`string`):** Palabras o frases entre comillas dobles (`\"EduGT\"`, `\"Jutiapa\"`).\n4. **Booleano (`bool`):** Solo dos posibles estados: `true` (Verdadero) o `false` (Falso).\n\n> ⚠️ **Diferencia clave:** El número `10` es un Entero matemático. Pero `\"10\"` (con comillas) es un Texto y no se puede sumar directamente.",
                        'pseudocode' => [
                            'codigo' => "// Declaración y asignación de diferentes tipos de datos\nnombre_usuario = \"Elena\"\nedad_usuario = 17\npromedio_academico = 89.75\ncuenta_activa = true\n\nprint(\"Usuario registrado:\", nombre_usuario)\nprint(\"Edad:\", edad_usuario)\nprint(\"Promedio final:\", promedio_academico)\nprint(\"¿Estado activo?:\", cuenta_activa)",
                            'explicacion' => "Cada variable almacena un tipo específico: texto (string), entero (int), decimal (float) y booleano (bool con true/false).",
                            'reto' => [
                                'pregunta' => "Tienes las siguientes variables: `precio = 45.50`, `cantidad = 3`, `activo = false`, `nombre = \"Juan\"`. ¿A qué tipo de dato fundamental corresponde la variable `activo`?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'String (Cadena de texto)', 'es_correcta' => false, 'feedback' => 'Incorrecto: no está encerrada entre comillas dobles.'],
                                    ['id' => 'b', 'texto' => 'Booleano (bool: true / false)', 'es_correcta' => true, 'feedback' => '¡Correcto! Los valores booleanos solo pueden ser true o false.'],
                                    ['id' => 'c', 'texto' => 'Int (Entero)', 'es_correcta' => false, 'feedback' => 'Incorrecto: no representa una cantidad numérica.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Asignación y operaciones',
                        'markdown' => "## ⚡ Asignación y Operadores Aritméticos\n\nEl operador de asignación (`=`) evalúa todo lo que está a la **derecha** y guarda el resultado final en la variable de la **izquierda**.\n\n---\n\n### 🧮 Operadores Aritméticos Básicos:\n* **Suma (`+`):** `total = precio + iva`\n* **Resta (`-`):** `vidas = vidas - 1`\n* **Multiplicación (`*`):** `area = base * altura`\n* **División (`/`):** `promedio = suma / 3`\n* **Módulo (`%`):** Devuelve el residuo de una división. Ejemplo: `10 % 3` es `1`.\n\n---\n\n### 🎯 Jerarquía de Operaciones:\n1. Paréntesis `()`\n2. Multiplicación `*` y División `/`\n3. Suma `+` y Resta `-`\n\n> 📌 **Regla fundamental:** Un solo signo `=` sirve para **guardar datos**, mientras que el doble signo `==` sirve para **comparar**.",
                        'pseudocode' => [
                            'codigo' => "nota1 = 85\nnota2 = 95\nnota3 = 70\n\n// Los paréntesis aseguran el cálculo correcto del promedio\npromedio = (nota1 + nota2 + nota3) / 3\n\n// Incrementar bonificación de 5 puntos\npromedio = promedio + 5\n\nprint(\"Promedio general con bono:\", promedio)",
                            'explicacion' => "Primero se suman las notas entre paréntesis (250), se divide entre 3 (83.33) y luego se agregan 5 puntos de bono (88.33).",
                            'reto' => [
                                'pregunta' => "Analiza la siguiente operación: `resultado = 10 + 5 * 2`. ¿Cuál será el valor final almacenado en `resultado`?",
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
                        'markdown' => "## 🏆 Hito del Módulo: Variables y Datos\n\n¡Has completado todas las lecciones sobre variables, tipos y operadores!\n\n---\n\n### 📋 Repaso de conceptos clave:\n* Almacenar datos en memoria con nombres descriptivos usando `=`. \n* Elegir el tipo de dato adecuado (`int`, `float`, `string` o `bool`).\n* Respetar la precedencia matemática con paréntesis `()`.\n\n> 🎯 **Siguiente Reto:** Presenta la evaluación del módulo. Con un **70% o más** desbloquearás la insignia **Novato en Lógica** y el Módulo 2.",
                        'pseudocode' => [
                            'codigo' => "// Desafío integrador de variables\nx = 10\ny = 20\nx = x + y\ny = x - y\nx = x - y\n\nprint(\"Valor final de x:\", x)  // Imprime 20\nprint(\"Valor final de y:\", y)  // Imprime 10",
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
                        'statement' => "Analiza el siguiente código:\n\n```\na = 15\nb = 10\na = a + 5\nresultado = a - b\nprint(resultado)\n```\n\n¿Qué valor numérico se imprimirá en pantalla al ejecutar el algoritmo?",
                        'explanation' => "Primero la variable `a` se actualiza: 15 + 5 = 20. Luego se calcula `resultado = 20 - 10 = 10`.",
                        'options' => [
                            ['texto' => '10', 'correcta' => true, 'feedback' => '¡Correcto! `a` pasa de 15 a 20, luego `resultado = 20 - 10 = 10`.'],
                            ['texto' => '5', 'correcta' => false, 'feedback' => 'Incorrecto: recuerda que a la variable `a` se le sumaron 5 unidades antes de la resta.'],
                            ['texto' => '20', 'correcta' => false, 'feedback' => 'Incorrecto: 20 es el valor intermedio de `a`, no el valor de `resultado`.']
                        ]
                    ],
                    [
                        'statement' => "Un sistema escolar necesita registrar si un estudiante ya pagó su matrícula escolar. ¿Cuál es el **tipo de dato más apropiado** para almacenar esta información?",
                        'explanation' => "Los datos booleanos (`true` / `false`) son los idóneos para representar estados binarios como sí/no o activo/inactivo.",
                        'options' => [
                            ['texto' => 'Booleano / bool (true o false)', 'correcta' => true, 'feedback' => '¡Correcto! Es perfecto para estados binarios de sí/no o pagado/no pagado.'],
                            ['texto' => 'String / Cadena ("Sí" o "No")', 'correcta' => false, 'feedback' => 'Incorrecto: aunque es posible, un string no es el tipo óptimo ni estándar para estados booleanos.'],
                            ['texto' => 'Entero / int (1 o 0)', 'correcta' => false, 'feedback' => 'Incorrecto: los tipos booleanos expresan formalmente valores de verdad en programación.']
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
                        'explanation' => "No es posible realizar una suma matemática entre una cadena de texto (string) y un número entero (int).",
                        'options' => [
                            ['texto' => 'edad = "dieciséis" + 5', 'correcta' => true, 'feedback' => '¡Correcto! No se puede sumar aritméticamente un número entero a un string de texto.'],
                            ['texto' => 'promedio = 85.5 + 10', 'correcta' => false, 'feedback' => 'Incorrecto: sumar un número float con un int es una operación aritmética válida.'],
                            ['texto' => 'es_mayor = true', 'correcta' => false, 'feedback' => 'Incorrecto: asignar un valor booleano true a una variable es totalmente válido.']
                        ]
                    ]
                ]
            ],
            [
                'module_title' => 'Estructuras Condicionales',
                'lessons' => [
                    [
                        'title' => 'La decisión if (Condicional simple)',
                        'markdown' => "## 🔀 La Decisión `if` (Condicional Simple)\n\nEn el desarrollo de software profesional tomamos decisiones basadas en condiciones: si un valor cumple una regla, ejecutamos una acción. En lenguajes de programación reales como Python, JavaScript, Java, C++ y PHP, las computadoras evalúan estas decisiones utilizando la instrucción **`if`**.\n\nUna estructura condicional evalúa una **expresión lógica** o booleana:\n* Si la condición evalúa a **`true`**, el programa ejecuta las instrucciones dentro del bloque del `if`.\n* Si evalúa a **`false`**, la computadora simplemente se salta ese bloque y continúa con el flujo del programa.\n\n---\n\n### ⚖️ Operadores de Comparación Estándar:\n* `==` : Igual a (compara si dos valores son idénticos)\n* `!=` : Diferente de (distinto de)\n* `>` : Mayor que | `<` : Menor que\n* `>=` : Mayor o igual que | `<=` : Menor o igual que",
                        'pseudocode' => [
                            'codigo' => "puntos = 120\n\nif puntos >= 100:\n    print(\"¡Felicitaciones! Has subido de nivel.\")\n    print(\"Obtienes una bonificación de +20 XP.\")\n\nprint(\"Continuando la aventura...\")",
                            'explicacion' => "Como la condición (120 >= 100) evalúa a true, se ejecuta el bloque interior del 'if' y muestra los mensajes con print.",
                            'reto' => [
                                'pregunta' => "Si tenemos la variable `temperatura = 32` y ejecutamos: `if temperatura > 30: print(\"¡Hace calor!\")`. ¿Qué resultado se mostrará en pantalla?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '¡Hace calor!', 'es_correcta' => true, 'feedback' => '¡Correcto! Como 32 > 30 evalúa a true, el bloque interior del if se ejecuta.'],
                                    ['id' => 'b', 'texto' => 'Nada (la pantalla queda vacía)', 'es_correcta' => false, 'feedback' => 'Incorrecto: la condición 32 > 30 es true, por lo que la función print sí se ejecuta.'],
                                    ['id' => 'c', 'texto' => 'Error de sintaxis', 'es_correcta' => false, 'feedback' => 'Incorrecto: la estructura condicional if está escrita con la sintaxis correcta.']
                                ]
                            ]
                        ],
                        'minutes' => 8,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Estructura if - else',
                        'markdown' => "## ⚖️ Estructura `if` - `else` (Alternativa Doble)\n\n¿Qué ocurre cuando necesitas que el programa ejecute una acción si la condición se cumple, pero **otra acción totalmente distinta** si no se cumple?\n\nEn la programación profesional nunca traducimos al español; empleamos la palabra clave en inglés **`else`**.\n\n---\n\n### 📌 Estructura Fundamental:\n```python\nif condicion:\n    # Se ejecuta exclusivamente si la condición es true\nelse:\n    # Se ejecuta exclusivamente si la condición es false\n```\n\n> 💡 **Regla de oro:** En una estructura `if` - `else`, las dos ramas son mutuamente excluyentes: **solo una de las dos ramas** se ejecutará en cada ejecución, nunca ambas.",
                        'pseudocode' => [
                            'codigo' => "calificacion = 58\n\nif calificacion >= 60:\n    print(\"Estado: Aprobado\")\n    print(\"¡Excelente trabajo!\")\nelse:\n    print(\"Estado: Reprobado\")\n    puntos_faltantes = 60 - calificacion\n    print(\"Te faltaron\", puntos_faltantes, \"puntos para aprobar.\")",
                            'explicacion' => "Como 58 >= 60 evalúa a false, el flujo salta la rama del if y ejecuta únicamente el bloque else.",
                            'reto' => [
                                'pregunta' => "Dado el código: `edad = 15`. `if edad >= 18: print(\"Mayor\") else: print(\"Menor\")`. ¿Qué rama se ejecutará?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Ambas ramas de forma consecutiva', 'es_correcta' => false, 'feedback' => 'Incorrecto: las ramas if y else son mutuamente excluyentes; solo una se ejecuta.'],
                                    ['id' => 'b', 'texto' => 'La rama else, imprimiendo \"Menor\"', 'es_correcta' => true, 'feedback' => '¡Exacto! Como 15 >= 18 evalúa a false, el flujo salta directamente al bloque else.'],
                                    ['id' => 'c', 'texto' => 'La rama if, imprimiendo \"Mayor\"', 'es_correcta' => false, 'feedback' => 'Incorrecto: la condición edad >= 18 evalúa a false con edad = 15.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Condicionales anidadas y operadores (if - else if)',
                        'markdown' => "## 🧩 Condicionales Anidadas y Operadores Lógicos\n\nA menudo, una sola comparación no basta para clasificar datos complejos. Para crear decisiones avanzadas combinamos múltiples condiciones mediante **operadores lógicos** y estructuras encadenadas:\n\n---\n\n### 🧠 Operadores Lógicos Estándar:\n* **`and` (Y lógico):** Todas las condiciones deben evaluar a `true` para que la expresión completa sea `true`.\n* **`or` (O lógico):** Basta con que al menos UNA de las condiciones sea `true` para que la expresión sea `true`.\n* **`not` (Negación):** Invierte el valor booleano (`not true` es `false`, y `not false` es `true`).\n\n---\n\n### 📂 Decisiones en Cascada (`if` - `else if` / `elif`):\nPermiten clasificar datos en múltiples categorías ordenadas (por ejemplo: escalas de notas A, B, C, D).",
                        'pseudocode' => [
                            'codigo' => "nota = 88\n\nif nota >= 90:\n    print(\"Rango: Excelente (A)\")\nelse:\n    if nota >= 80 and nota < 90:\n        print(\"Rango: Muy Bueno (B)\")\n    else:\n        if nota >= 60:\n            print(\"Rango: Aprobado (C)\")\n        else:\n            print(\"Rango: Necesita Refuerzo (D)\")",
                            'explicacion' => "Con nota = 88, la primera condición nota >= 90 es false, pero la rama else evalúa (nota >= 80 and nota < 90), la cual resulta true imprimiendo 'Muy Bueno (B)'.",
                            'reto' => [
                                'pregunta' => "Evalúa la siguiente expresión lógica combinada: `(5 > 3) and (10 < 8)`. ¿Cuál es el resultado booleano final?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'true', 'es_correcta' => false, 'feedback' => 'Incorrecto: el operador and exige que AMBAS condiciones sean true, pero 10 < 8 es false.'],
                                    ['id' => 'b', 'texto' => 'false', 'es_correcta' => true, 'feedback' => '¡Muy bien! (5 > 3) es true, pero (10 < 8) es false. true and false produce false.'],
                                    ['id' => 'c', 'texto' => 'null / Indeterminado', 'es_correcta' => false, 'feedback' => 'Incorrecto: toda expresión booleana tiene un valor definido de true o false.']
                                ]
                            ]
                        ],
                        'minutes' => 12,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Evaluación del módulo',
                        'markdown' => "## 🏆 Hito del Módulo: Estructuras Condicionales\n\n¡Has dominado el control de flujo con la sintaxis y terminología real de la industria: `if`, `else`, `and`, `or` y `not`!\n\n---\n\n### 📋 Repaso de conceptos clave:\n* Sentencia `if` para decisiones simples cuando una condición evalúa a `true`.\n* Bifurcación doble con `if` - `else` para caminos alternativos.\n* Combinación de condiciones lógicas con `and`, `or` y negación con `not`.\n\n> 🎯 **Siguiente Reto:** Presenta la evaluación del módulo (mínimo 70%) para desbloquear la insignia **Mago del Control** y el Módulo 3.",
                        'pseudocode' => [
                            'codigo' => "hora = 14\nes_dia_laboral = true\n\nif es_dia_laboral and (hora >= 8 and hora <= 17):\n    print(\"Horario de atención disponible.\")\nelse:\n    print(\"Oficina cerrada fuera de horario.\")",
                            'explicacion' => "Verificación de rangos horarios con operadores lógicos combinados 'and'. Ambas condiciones son true, ejecutando el bloque if.",
                            'reto' => [
                                'pregunta' => "En un sistema de acceso: `tiene_pase = false`, `es_admin = true`. La condición es `if tiene_pase or es_admin: print(\"Acceso Permitido\")`. ¿Qué ocurrirá?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Mostrará \"Acceso Permitido\", porque con el operador or basta con que una condición sea true.', 'es_correcta' => true, 'feedback' => '¡Excelente! Al ser `es_admin` true, la condición completa del operador or se cumple.'],
                                    ['id' => 'b', 'texto' => 'No mostrará nada, porque `tiene_pase` es false.', 'es_correcta' => false, 'feedback' => 'Incorrecto: el operador or no requiere que ambas sean verdaderas, basta con que una lo sea.'],
                                    ['id' => 'c', 'texto' => 'Dará un error por tener dos variables con estados booleanos distintos.', 'es_correcta' => false, 'feedback' => 'Incorrecto: combinar variables booleanas con operadores lógicos es una práctica estándar y válida.']
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
                        'statement' => "Analiza el siguiente fragmento de código condicional:\n\n```\npuntos = 85\nif puntos >= 90:\n    mensaje = \"Nivel Oro\"\nelse:\n    if puntos >= 75:\n        mensaje = \"Nivel Plata\"\n    else:\n        mensaje = \"Nivel Bronce\"\nprint(mensaje)\n```\n\n¿Qué texto mostrará la pantalla?",
                        'explanation' => "Como `85 >= 90` es false, pasa al bloque `else`. Allí `85 >= 75` es true, asignando `\"Nivel Plata\"`.",
                        'options' => [
                            ['texto' => 'Nivel Plata', 'correcta' => true, 'feedback' => '¡Correcto! 85 no es >= 90, pero sí cumple la segunda condición `85 >= 75`.'],
                            ['texto' => 'Nivel Oro', 'correcta' => false, 'feedback' => 'Incorrecto: 85 no es mayor o igual a 90.'],
                            ['texto' => 'Nivel Bronce', 'correcta' => false, 'feedback' => 'Incorrecto: el flujo entra a la rama `Nivel Plata` antes de llegar a Bronce.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el valor booleano resultante de evaluar la siguiente expresión lógica combinada?\n\n```\nresultado = (10 > 5) and (3 == 4)\n```",
                        'explanation' => "La primera condición `(10 > 5)` es true, pero la segunda `(3 == 4)` es false. En una conjunción (`and`), `true and false` produce `false`.",
                        'options' => [
                            ['texto' => 'false', 'correcta' => true, 'feedback' => '¡Correcto! `10 > 5` es true, pero `3 == 4` es false. Con el operador `and`, ambas deben ser verdaderas.'],
                            ['texto' => 'true', 'correcta' => false, 'feedback' => 'Incorrecto: con el operador `and`, si una sola condición es falsa, toda la expresión es false.'],
                            ['texto' => 'Error de sintaxis', 'correcta' => false, 'feedback' => 'Incorrecto: la expresión lógica de comparación y conjunción está escrita correctamente.']
                        ]
                    ],
                    [
                        'statement' => "Para ingresar a una atracción mecánica se requiere que el visitante tenga **al menos 12 años** O que **venga acompañado por un tutor adulto**. Si `edad = 10` y `acompanado = true`, ¿qué expresión lógica evalúa correctamente esta regla?",
                        'explanation' => "La regla utiliza una disyunción (`or`): basta con tener 12 o más años, o bien venir acompañado por un tutor.",
                        'options' => [
                            ['texto' => 'edad >= 12 or acompanado == true', 'correcta' => true, 'feedback' => '¡Correcto! El operador `or` permite el ingreso si se cumple cualquiera de las dos condiciones.'],
                            ['texto' => 'edad >= 12 and acompanado == true', 'correcta' => false, 'feedback' => 'Incorrecto: el operador `and` obligaría a tener 12 años y además venir acompañado obligatoriamente.'],
                            ['texto' => 'edad == 12 or acompanado == false', 'correcta' => false, 'feedback' => 'Incorrecto: no representa fielmente las condiciones de la regla.']
                        ]
                    ],
                    [
                        'statement' => "Observa el siguiente algoritmo de cajero automático:\n\n```\nsaldo = 50\nmonto_retiro = 80\n\nif monto_retiro <= saldo:\n    saldo = saldo - monto_retiro\n    print(\"Retiro exitoso\")\nelse:\n    print(\"Fondos insuficientes\")\n\nprint(\"Saldo actual:\", saldo)\n```\n\n¿Qué dos líneas de salida se imprimirán en pantalla?",
                        'explanation' => "`80 <= 50` es false, por lo que entra al bloque `else` imprimiendo 'Fondos insuficientes' y el `saldo` se mantiene intacto en 50.",
                        'options' => [
                            ['texto' => '"Fondos insuficientes" y "Saldo actual: 50"', 'correcta' => true, 'feedback' => '¡Correcto! 80 <= 50 es false, se ejecuta el else y el saldo no se altera.'],
                            ['texto' => '"Retiro exitoso" y "Saldo actual: -30"', 'correcta' => false, 'feedback' => 'Incorrecto: la condición condicional previene que el saldo quede en negativo.'],
                            ['texto' => '"Fondos insuficientes" y "Saldo actual: 0"', 'correcta' => false, 'feedback' => 'Incorrecto: el saldo no se redujo a cero porque no se ejecutó el retiro.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el efecto del operador lógico `not` al aplicarse sobre una variable o expresión booleana?",
                        'explanation' => "El operador `not` niega o invierte el valor de verdad: convierte `true` en `false`, y `false` en `true`.",
                        'options' => [
                            ['texto' => 'Invierte el valor de verdad (true pasa a false y viceversa)', 'correcta' => true, 'feedback' => '¡Correcto! Es la operación de negación lógica unaria estándar.'],
                            ['texto' => 'Compara si dos números son exactamente iguales', 'correcta' => false, 'feedback' => 'Incorrecto: esa es la función del operador de comparación `==`.'],
                            ['texto' => 'Borra la variable de la memoria de la computadora', 'correcta' => false, 'feedback' => 'Incorrecto: `not` es un operador lógico booleano, no una instrucción de eliminación.']
                        ]
                    ]
                ]
            ],
            [
                'module_title' => 'Estructuras Cíclicas (Bucles)',
                'lessons' => [
                    [
                        'title' => 'El bucle while (Repetición condicional)',
                        'markdown' => "## 🔄 El Bucle `while` (Repetición Condicional)\n\nEn programación, muchas veces necesitamos repetir un bloque de instrucciones varias veces sin escribir el mismo código una y otra vez. Para ello existen las **estructuras cíclicas** o **bucles**.\n\nEl bucle **`while`** repite su bloque de código **mientras** una condición lógica evalúe a **`true`**.\n\n---\n\n### 🔑 Los 3 Elementos Esenciales de un `while`:\n1. **Inicialización:** Definir la variable de control antes de iniciar el bucle (ej. `contador = 1`).\n2. **Condición de permanencia:** Expresión booleana que se evalúa antes de cada vuelta (ej. `while contador <= 3:`).\n3. **Actualización (Incremento/Decremento):** Modificar la variable dentro del bucle (`contador = contador + 1`) para que en algún momento la condición sea `false` y el bucle termine.\n\n> ⚠️ **Cuidado con el Bucle Infinito:** Si olvidas actualizar la variable de control dentro del `while`, la condición siempre será `true` y el programa nunca terminará.",
                        'pseudocode' => [
                            'codigo' => "contador = 1\n\nwhile contador <= 3:\n    print(\"Vuelta número:\", contador)\n    contador = contador + 1\n\nprint(\"¡Bucle finalizado con éxito!\")",
                            'explicacion' => "El bucle inicia con contador = 1. Imprime 1, 2 y 3 en cada iteración. Cuando contador llega a 4, la condición (4 <= 3) evalúa a false y el bucle termina.",
                            'reto' => [
                                'pregunta' => "Analiza el código: `energia = 3`. `while energia > 0: print(energia); energia = energia - 1`. ¿Cuántas veces se ejecutará la instrucción `print(energia)`?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '3 veces (imprimiendo 3, 2 y 1)', 'es_correcta' => true, 'feedback' => '¡Excelente! Se ejecuta para energia = 3, 2 y 1. Cuando llega a 0, la condición `0 > 0` es false y se detiene.'],
                                    ['id' => 'b', 'texto' => '4 veces (imprimiendo 3, 2, 1 y 0)', 'es_correcta' => false, 'feedback' => 'Incorrecto: la condición es estrictamente mayor que cero (`energia > 0`), por lo que con 0 ya no entra al bucle.'],
                                    ['id' => 'c', 'texto' => 'Infinitas veces', 'es_correcta' => false, 'feedback' => 'Incorrecto: como `energia` disminuye en 1 en cada vuelta, el bucle se detiene al llegar a 0.']
                                ]
                            ]
                        ],
                        'minutes' => 8,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'El bucle for y rangos (Iteración definida)',
                        'markdown' => "## 🔁 El Bucle `for` (Iteración Definida)\n\nCuando sabemos de antemano **exactamente cuántas veces** queremos repetir una acción, los programadores profesionales utilizan el bucle **`for`**.\n\nA diferencia de `while`, el bucle `for` gestiona automáticamente el inicio, el avance y el final del recorrido utilizando una secuencia o rango (`range`).\n\n---\n\n### 📌 Cómo funciona `range()`:\n* **`for i in range(4):`** Repite el bloque **4 veces**, tomando `i` los valores `0, 1, 2, 3`.\n* **`for i in range(1, 5):`** Inicia en `1` y se detiene antes del `5`, tomando `i` los valores `1, 2, 3, 4`.\n\n> 💡 **Regla de oro:** Usa **`for`** cuando conozcas el número exacto de repeticiones, y usa **`while`** cuando la repetición dependa de una condición que puede cambiar dinámicamente.",
                        'pseudocode' => [
                            'codigo' => "// Tabla de multiplicar del 5 (del 1 al 4)\nfor numero in range(1, 5):\n    resultado = 5 * numero\n    print(\"5 x\", numero, \"=\", resultado)\n\nprint(\"Tabla completada.\")",
                            'explicacion' => "La función range(1, 5) genera la secuencia 1, 2, 3 y 4. En cada vuelta, la variable 'numero' toma el siguiente valor automáticamente.",
                            'reto' => [
                                'pregunta' => "Si ejecutamos el bucle: `for i in range(5): print(\"Hola EduGT\")`. ¿Cuántas veces se mostrará el mensaje en pantalla?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '4 veces', 'es_correcta' => false, 'feedback' => 'Incorrecto: aunque `i` llega hasta el índice 4, empieza a contar desde el 0 (0, 1, 2, 3, 4).'],
                                    ['id' => 'b', 'texto' => '5 veces', 'es_correcta' => true, 'feedback' => '¡Correcto! `range(5)` genera 5 iteraciones correspondientes a los valores 0, 1, 2, 3 y 4.'],
                                    ['id' => 'c', 'texto' => '6 veces', 'es_correcta' => false, 'feedback' => 'Incorrecto: el número límite de `range(5)` no se incluye, por lo que ejecuta exactamente 5 vueltas.']
                                ]
                            ]
                        ],
                        'minutes' => 10,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Contadores, acumuladores y control de bucles',
                        'markdown' => "## 🧮 Contadores, Acumuladores y `break`\n\nPara resolver problemas reales con bucles (como calcular el promedio de notas de un aula o sumar el total de una compra), combinamos los ciclos con dos patrones clásicos de variables:\n\n---\n\n### 📊 Contador vs. Acumulador:\n* **Contador (`cuenta = cuenta + 1`):** Aumenta de **1 en 1** (o un valor fijo) para contar cuántos elementos cumplen una condición.\n* **Acumulador (`suma = suma + valor`):** Suma valores **variables** en cada vuelta para obtener un total acumulado.\n\n---\n\n### 🛑 La instrucción `break`:\nPermite **detener y salir inmediatamente** de un bucle antes de que termine sus vueltas normales (por ejemplo, cuando ya encontramos el dato que buscábamos).",
                        'pseudocode' => [
                            'codigo' => "suma_total = 0\n\nfor i in range(1, 4):\n    suma_total = suma_total + (i * 10)\n    print(\"Iteración\", i, \"-> Acumulado:\", suma_total)\n\nprint(\"Suma final:\", suma_total)",
                            'explicacion' => "En la vuelta 1 suma 10 (total 10). En la vuelta 2 suma 20 (total 30). En la vuelta 3 suma 30 (total 60).",
                            'reto' => [
                                'pregunta' => "Tenemos `total = 0` y ejecutamos `for n in range(1, 4): total = total + n`. ¿Qué valor tendrá la variable acumuladora `total` al finalizar el bucle?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => '3', 'es_correcta' => false, 'feedback' => 'Incorrecto: 3 es el último valor de `n`, pero `total` fue acumulando la suma de cada vuelta.'],
                                    ['id' => 'b', 'texto' => '6', 'es_correcta' => true, 'feedback' => '¡Brillante! `range(1, 4)` recorre 1, 2 y 3. La suma acumulada es 1 + 2 + 3 = 6.'],
                                    ['id' => 'c', 'texto' => '10', 'es_correcta' => false, 'feedback' => 'Incorrecto: recuerda que `range(1, 4)` se detiene antes del 4, por lo que solo suma 1 + 2 + 3.']
                                ]
                            ]
                        ],
                        'minutes' => 12,
                        'xp' => 10,
                        'requires_quiz' => false,
                    ],
                    [
                        'title' => 'Evaluación del módulo',
                        'markdown' => "## 🏆 Hito del Módulo: Estructuras Cíclicas (Bucles)\n\n¡Felicidades! Has completado el recorrido por las estructuras repetitivas: `while`, `for`, `range()`, contadores, acumuladores y control de iteraciones.\n\n---\n\n### 📋 Repaso de conceptos clave:\n* Bucle `while` para repetir instrucciones mientras una condición sea `true`.\n* Bucle `for` con `range()` para iteraciones con cantidad conocida de vueltas.\n* Uso de **contadores** y **acumuladores** para procesar colecciones de datos sin caer en bucles infinitos.\n\n> 🎯 **Siguiente Reto:** Presenta la evaluación del módulo (mínimo 70%) para desbloquear la insignia **Maestro del Bucle** y completar la Misión 1.",
                        'pseudocode' => [
                            'codigo' => "pares_encontrados = 0\n\nfor num in range(1, 7):\n    if num % 2 == 0:\n        pares_encontrados = pares_encontrados + 1\n        print(\"Número par detectado:\", num)\n\nprint(\"Total de números pares:\", pares_encontrados)",
                            'explicacion' => "Combina un bucle for del 1 al 6 con un condicional if que verifica si el residuo al dividir entre 2 es 0 (2, 4 y 6), contando 3 números pares.",
                            'reto' => [
                                'pregunta' => "¿Qué ocurre si ejecutamos un bucle `while x < 5: print(x)` donde inicialmente `x = 1`, pero **olvidamos** escribir `x = x + 1` dentro del bucle?",
                                'opciones' => [
                                    ['id' => 'a', 'texto' => 'Se produce un bucle infinito porque `x` siempre valdrá 1 y la condición `1 < 5` nunca dejará de ser true.', 'es_correcta' => true, 'feedback' => '¡Exacto! Sin actualizar la variable de control, la condición jamás se vuelve false.'],
                                    ['id' => 'b', 'texto' => 'Imprime el número 1 una sola vez y el programa finaliza normalmente.', 'es_correcta' => false, 'feedback' => 'Incorrecto: eso ocurriría con un `if`, pero `while` vuelve a evaluar la condición una y otra vez.'],
                                    ['id' => 'c', 'texto' => 'La computadora incrementa `x` automáticamente aunque no esté escrito en el código.', 'es_correcta' => false, 'feedback' => 'Incorrecto: a diferencia de `for`, en un bucle `while` el programador debe actualizar explícitamente la variable.']
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
                        'statement' => "Analiza el siguiente bucle `while`:\n\n```\nx = 2\nwhile x <= 8:\n    print(x)\n    x = x + 3\n```\n\n¿Qué secuencia de números se imprimirá en pantalla?",
                        'explanation' => "Inicia en `x = 2` (imprime 2), suma 3 y pasa a `5` (imprime 5), suma 3 y pasa a `8` (imprime 8), suma 3 y pasa a `11` (`11 <= 8` es false y termina).",
                        'options' => [
                            ['texto' => '2, 5, 8', 'correcta' => true, 'feedback' => '¡Correcto! Imprime 2, luego 2 + 3 = 5, luego 5 + 3 = 8. Al pasar a 11, `11 <= 8` es false y finaliza.'],
                            ['texto' => '2, 5, 8, 11', 'correcta' => false, 'feedback' => 'Incorrecto: cuando `x` toma el valor 11, la condición `x <= 8` evalúa a false y ya no imprime.'],
                            ['texto' => '2, 3, 4, 5, 6, 7, 8', 'correcta' => false, 'feedback' => 'Incorrecto: observa que el incremento en cada vuelta es de 3 en 3 (`x = x + 3`), no de 1 en 1.']
                        ]
                    ],
                    [
                        'statement' => "¿Cuál es el valor final de la variable `acumulador` tras ejecutar el siguiente bucle `for`?\n\n```\nacumulador = 10\nfor i in range(3):\n    acumulador = acumulador + 5\nprint(acumulador)\n```",
                        'explanation' => "`range(3)` ejecuta el bloque exactamente 3 veces. En cada vuelta se suman 5 a los 10 iniciales: `10 + 5 + 5 + 5 = 25`.",
                        'options' => [
                            ['texto' => '15', 'correcta' => false, 'feedback' => 'Incorrecto: 15 es el valor tras la primera vuelta, pero el bucle se repite 3 veces.'],
                            ['texto' => '25', 'correcta' => true, 'feedback' => '¡Correcto! Inicia en 10 y suma 5 en cada una de las 3 iteraciones: 10 + 15 = 25.'],
                            ['texto' => '30', 'correcta' => false, 'feedback' => 'Incorrecto: `range(3)` ejecuta 3 iteraciones (índices 0, 1 y 2), no 4.']
                        ]
                    ],
                    [
                        'statement' => "¿Por qué el siguiente fragmento de código provoca un **bucle infinito**?\n\n```\nintentos = 1\nwhile intentos <= 5:\n    print(\"Verificando conexión...\")\n```",
                        'explanation' => "La variable `intentos` vale 1 y nunca se modifica dentro del bloque `while`, por lo que `intentos <= 5` siempre es `true`.",
                        'options' => [
                            ['texto' => 'Porque falta actualizar la variable `intentos` dentro del bucle (ej. `intentos = intentos + 1`)', 'correcta' => true, 'feedback' => '¡Exacto! Al no incrementar `intentos`, siempre vale 1 y la condición jamás deja de cumplirse.'],
                            ['texto' => 'Porque la función `print` reinicia la variable `intentos` a cero en cada vuelta', 'correcta' => false, 'feedback' => 'Incorrecto: `print` únicamente muestra texto en pantalla y no altera variables.'],
                            ['texto' => 'Porque el operador `<=` no puede utilizarse dentro de una instrucción `while`', 'correcta' => false, 'feedback' => 'Incorrecto: el operador `<=` es totalmente válido en condiciones de bucles `while`.']
                        ]
                    ],
                    [
                        'statement' => "Observa el siguiente algoritmo que combina un bucle `for` con una estructura condicional `if`:\n\n```\ncontador_mayores = 0\nfor n in range(1, 6):\n    if n > 3:\n        contador_mayores = contador_mayores + 1\nprint(contador_mayores)\n```\n\n¿Qué número imprimirá al final?",
                        'explanation' => "`range(1, 6)` genera los números `1, 2, 3, 4, 5`. De ellos, solo `4` y `5` cumplen la condición `n > 3`, por lo que el contador aumenta 2 veces.",
                        'options' => [
                            ['texto' => '3', 'correcta' => false, 'feedback' => 'Incorrecto: el número 3 no cumple `3 > 3`, y el 6 no está incluido en `range(1, 6)`.'],
                            ['texto' => '2', 'correcta' => true, 'feedback' => '¡Correcto! Del 1 al 5, únicamente los valores 4 y 5 son mayores que 3.'],
                            ['texto' => '5', 'correcta' => false, 'feedback' => 'Incorrecto: 5 es el total de vueltas, pero el contador solo incrementa cuando `n > 3`.']
                        ]
                    ],
                    [
                        'statement' => "¿En qué situación es más recomendable utilizar un bucle **`for`** en lugar de un bucle **`while`**?",
                        'explanation' => "El bucle `for` está diseñado para recorrer secuencias o rangos cuando conocemos de antemano el número exacto de iteraciones.",
                        'options' => [
                            ['texto' => 'Cuando nunca queremos que el bloque de código se repita más de una vez', 'correcta' => false, 'feedback' => 'Incorrecto: si no se requiere repetición, se usa una estructura secuencial o condicional `if`.'],
                            ['texto' => 'Cuando desconocemos por completo cuántas veces se repetirá y dependemos de que el usuario escriba "salir"', 'correcta' => false, 'feedback' => 'Incorrecto: para repeticiones indefinidas basadas en un evento externo es ideal el bucle `while`.'],
                            ['texto' => 'Cuando sabemos exactamente cuántas veces queremos repetir el bloque o recorremos un rango fijo', 'correcta' => true, 'feedback' => '¡Correcto! El bucle `for` es la herramienta ideal para iteraciones definidas y rangos conocidos.']
                        ]
                    ]
                ]
            ]
        ];

        foreach ($curriculumData as $i => $modData) {
            $module = Module::query()->updateOrCreate(
                ['mission_id' => $mission->id, 'order_index' => $i],
                [
                    'title' => $modData['module_title'],
                    'required_level' => $i + 1,
                    'status' => 'published',
                ]
            );

            foreach ($modData['lessons'] as $n => $lData) {
                Lesson::query()->updateOrCreate(
                    ['module_id' => $module->id, 'order_index' => $n],
                    [
                        'title' => $lData['title'],
                        'content_markdown' => $lData['markdown'],
                        'pseudocode_json' => $lData['pseudocode'],
                        'estimated_minutes' => $lData['minutes'],
                        'xp_reward' => $lData['xp'],
                        'requires_quiz_pass' => $lData['requires_quiz'],
                        'status' => 'published',
                    ]
                );
            }

            $quiz = Quiz::query()->updateOrCreate(
                ['module_id' => $module->id],
                [
                    'title' => "Evaluación: {$modData['module_title']}",
                    'passing_score' => 70,
                ]
            );

            foreach ($modData['quiz_questions'] as $qn => $qData) {
                $q = Question::query()->updateOrCreate(
                    ['quiz_id' => $quiz->id, 'order_index' => $qn + 1],
                    [
                        'statement' => $qData['statement'],
                        'explanation' => $qData['explanation'],
                    ]
                );

                foreach ($qData['options'] as $oi => $opt) {
                    QuestionOption::query()->updateOrCreate(
                        ['question_id' => $q->id, 'order_index' => $oi],
                        [
                            'option_text' => $opt['texto'],
                            'is_correct' => $opt['correcta'],
                            'feedback' => $opt['feedback'],
                        ]
                    );
                }
            }
        }
    }

    /**
     * RB-17: pretest y postest con la MISMA estructura — 14 preguntas (12-15 recomendado),
     * 2 por cada una de las 7 dimensiones del documento, utilizando formas paralelas
     * isomórficas para evitar sesgo de memorización entre el diagnóstico inicial y el final.
     */
    private function seedDiagnostics(): void
    {
        $dimensions = [
            'abstraction', 'decomposition', 'pattern_recognition',
            'algorithms', 'variables', 'conditionals', 'loops',
        ];

        // ---------------- PRE-TEST (Reactivos 1 y 2 por dimensión) ----------------
        $preDemo = [
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
                'statement' => 'En código: `edad = 15`, luego `edad = edad + 1`. ¿Cuánto vale `edad` al final?',
                'options' => ['16', '15', 'No se puede saber'],
                'correct' => 0,
                'explanation' => 'La variable se actualiza: 15 + 1 = 16.',
            ],
            'conditionals' => [
                'statement' => 'Dado el código: `if nota >= 60: print("Aprobado") else: print("Reprobado")`. Con `nota = 60`, ¿qué imprime?',
                'options' => ['Aprobado', 'Reprobado', 'No imprime nada'],
                'correct' => 0,
                'explanation' => '60 >= 60 es true → entra por el bloque "if".',
            ],
            'loops' => [
                'statement' => 'Dado el código: `while i <= 3: print(i); i = i + 1` (iniciando `i = 1`). ¿Cuántas veces se ejecuta el print?',
                'options' => ['3 veces', '4 veces', 'Infinitas veces'],
                'correct' => 0,
                'explanation' => 'Se imprime con i = 1, 2, 3. Cuando i = 4 la condición es false y termina.',
            ],
        ];

        $preVariante = [
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
                'statement' => 'En código: `saldo = 50`, `saldo = saldo - 20`, `saldo = saldo * 2`. ¿Cuánto vale `saldo`?',
                'options' => ['60', '30', '20'],
                'correct' => 0,
                'explanation' => '50 - 20 = 30, luego 30 × 2 = 60.',
            ],
            'conditionals' => [
                'statement' => 'Dado el código: `if edad >= 18: print("Adulto") else: print("Menor")`. Con `edad = 17`, ¿qué imprime?',
                'options' => ['Menor', 'Adulto', 'Error'],
                'correct' => 0,
                'explanation' => '17 >= 18 es false → se ejecuta el bloque "else".',
            ],
            'loops' => [
                'statement' => 'Dado el bucle: `for i in range(4): print("EduGT")`. ¿Cuántas líneas se muestran en pantalla?',
                'options' => ['4', '3', '5'],
                'correct' => 0,
                'explanation' => 'El bucle for ejecuta el print exactamente 4 veces.',
            ],
        ];

        // ---------------- POST-TEST ISOMÓRFICO (Formas Paralelas Equivalentes) ----------------
        $postDemo = [
            'abstraction' => [
                'statement' => 'Para diseñar un sistema que controle la asistencia diaria en el instituto, ¿qué dato es INDISPENSABLE almacenar por cada alumno?',
                'options' => ['La fecha y si estuvo presente o ausente', 'La marca de su mochila escolar', 'Su deporte favorito'],
                'correct' => 0,
                'explanation' => 'La abstracción filtra detalles irrelevantes y conserva únicamente la fecha y el estado de asistencia.',
            ],
            'decomposition' => [
                'statement' => 'Debes crear un programa para cobrar en la tienda escolar aplicando descuento y dando vuelto. ¿Cómo descompones la tarea?',
                'options' => ['1) Calcular subtotal, 2) Restar descuento, 3) Calcular vuelto sobre el efectivo recibido', 'Cambiar el logotipo de la tienda y reiniciar el equipo', 'Cobrar siempre el mismo monto fijo sin importar los productos'],
                'correct' => 0,
                'explanation' => 'Descomponer divide el proceso de cobro en subproblemas secuenciales claros.',
            ],
            'pattern_recognition' => [
                'statement' => 'Analiza la sucesión numérica: 5, 10, 20, 40, 80... ¿qué regla se repite en cada paso?',
                'options' => ['Multiplicar por 2 el número anterior', 'Sumar 5 al número anterior', 'Restar 10 al número anterior'],
                'correct' => 0,
                'explanation' => 'Cada término se obtiene duplicando (multiplicando por 2) el valor previo.',
            ],
            'algorithms' => [
                'statement' => '¿Cuál es la secuencia lógica correcta de un algoritmo para enviar una tarea por correo electrónico?',
                'options' => ['Redactar mensaje → adjuntar archivo de la tarea → escribir destinatario → presionar Enviar', 'Presionar Enviar → apagar la computadora → adjuntar el archivo', 'Borrar el archivo → presionar Enviar → abrir el correo'],
                'correct' => 0,
                'explanation' => 'No se puede enviar el archivo correcto si antes no se adjunta e indica el destinatario.',
            ],
            'variables' => [
                'statement' => 'Si ejecutamos: `puntos = 20`, luego `puntos = puntos + 15`, ¿qué valor guarda la variable `puntos` al terminar?',
                'options' => ['35', '20', '15'],
                'correct' => 0,
                'explanation' => 'La variable toma su valor previo (20) y le suma 15, actualizándose a 35.',
            ],
            'conditionals' => [
                'statement' => 'Dado el código: `if bateria <= 15: print("Conectar cargador") else: print("Batería normal")`. Si `bateria = 15`, ¿qué muestra?',
                'options' => ['Conectar cargador', 'Batería normal', 'Ningún mensaje'],
                'correct' => 0,
                'explanation' => 'Como 15 <= 15 es verdadero (true), se ejecuta la rama del bloque "if".',
            ],
            'loops' => [
                'statement' => 'En el código: `paso = 1` y luego `while paso <= 4: print(paso); paso = paso + 1`, ¿cuántas veces se ejecuta `print`?',
                'options' => ['4 veces', '3 veces', '5 veces'],
                'correct' => 0,
                'explanation' => 'El ciclo se repite para paso = 1, 2, 3 y 4. Al llegar a 5 la condición es falsa y finaliza.',
            ],
        ];

        $postVariante = [
            'abstraction' => [
                'statement' => 'Una aplicación médica calcula si un paciente tiene fiebre. ¿Cuál es el único dato esencial para tomar esa decisión?',
                'options' => ['La temperatura corporal actual en grados', 'El color de la camisa del paciente', 'El número de sillas en la clínica'],
                'correct' => 0,
                'explanation' => 'Para determinar fiebre, el modelo computacional solo requiere la temperatura corporal.',
            ],
            'decomposition' => [
                'statement' => 'Quieres programar un reporte que indique cuántos alumnos aprobaron y cuántos reprobaron. ¿Cómo divides el problema?',
                'options' => ['Paso 1: revisar cada nota contra el mínimo (60), Paso 2: incrementar el contador de aprobados o reprobados', 'Paso 1: ordenar los nombres por estatura, Paso 2: borrar las notas bajas', 'Imprimir la lista sin revisar ninguna calificación'],
                'correct' => 0,
                'explanation' => 'Primero se clasifica cada caso y luego se acumula el conteo correspondiente.',
            ],
            'pattern_recognition' => [
                'statement' => 'Observa la secuencia: 45, 36, 27, 18, 9... ¿cuál es el patrón que transforma un número en el siguiente?',
                'options' => ['Restar 9 al número anterior', 'Dividir entre 3 el número anterior', 'Sumar 9 al número anterior'],
                'correct' => 0,
                'explanation' => 'En cada transición disminuye exactamente 9 unidades.',
            ],
            'algorithms' => [
                'statement' => 'Para retirar dinero en un cajero automático, ¿qué secuencia ordenada de pasos es la correcta?',
                'options' => ['Insertar tarjeta → ingresar PIN → elegir monto → retirar efectivo y tarjeta', 'Retirar efectivo → insertar tarjeta → elegir monto → ingresar PIN', 'Elegir monto → retirar tarjeta → ingresar PIN'],
                'correct' => 0,
                'explanation' => 'La autenticación con tarjeta y PIN debe ocurrir antes de autorizar y entregar el efectivo.',
            ],
            'variables' => [
                'statement' => 'Analiza la traza: `energia = 40`, `energia = energia - 10`, `energia = energia * 3`. ¿Cuál es el valor final de `energia`?',
                'options' => ['90', '30', '10'],
                'correct' => 0,
                'explanation' => 'Primero 40 - 10 = 30; después 30 * 3 = 90.',
            ],
            'conditionals' => [
                'statement' => 'Dado el código: `if temperatura > 30: print("Calor") else: print("Templado")`. Con `temperatura = 28`, ¿qué imprime?',
                'options' => ['Templado', 'Calor', 'Error de sintaxis'],
                'correct' => 0,
                'explanation' => '28 > 30 es falso (false), por lo que el flujo entra al bloque "else".',
            ],
            'loops' => [
                'statement' => 'Al ejecutar el ciclo: `for k in range(5): print("Hola")`, ¿cuántas veces aparece la palabra "Hola" en consola?',
                'options' => ['5', '4', '6'],
                'correct' => 0,
                'explanation' => 'range(5) genera 5 iteraciones (k = 0, 1, 2, 3, 4), imprimiendo 5 veces.',
            ],
        ];

        $rotacionesPorTipo = [
            'pre' => [0, 1, 2, 0, 2, 1, 0, 1, 2, 0, 2, 1, 0, 1],
            'post' => [1, 2, 0, 2, 0, 1, 2, 0, 1, 1, 0, 2, 2, 0],
        ];

        foreach (['pre' => 1, 'post' => 1] as $kind => $version) {
            $test = DiagnosticTest::query()->updateOrCreate(
                ['kind' => $kind, 'version' => $version],
                [
                    'question_count' => 14,
                    'time_limit_minutes' => 15,
                    'is_active' => true,
                ]
            );

            $rotaciones = $rotacionesPorTipo[$kind];

            foreach ($dimensions as $i => $dim) {
                $base = ($kind === 'post')
                    ? [$postDemo[$dim], $postVariante[$dim]]
                    : [$preDemo[$dim], $preVariante[$dim]];

                foreach ([1, 2] as $j) {
                    $d = $base[$j - 1];

                    $indiceGlobal = $i * 2 + $j - 1; // 0..13
                    $posCorrecta = $rotaciones[$indiceGlobal % count($rotaciones)];

                    $correcta = $d['options'][$d['correct']];
                    $distractoras = array_values(array_diff($d['options'], [$correcta]));
                    $final = [];
                    $di = 0;
                    for ($pos = 0; $pos < 3; $pos++) {
                        $final[$pos] = ($pos === $posCorrecta) ? $correcta : $distractoras[$di++];
                    }

                    DiagnosticQuestion::query()->updateOrCreate(
                        [
                            'diagnostic_test_id' => $test->id,
                            'order_index' => $i * 2 + $j,
                        ],
                        [
                            'dimension' => $dim,
                            'statement' => $d['statement'],
                            'options' => $final,
                            'correct_index' => $posCorrecta,
                            'explanation' => $d['explanation'],
                        ]
                    );
                }
            }
        }
    }

    private function seedLabChallenges(): void
    {
        if (! Schema::hasTable('lab_challenges')) {
            return;
        }

        $modules = Module::query()->orderBy('order_index')->get();
        $mod1 = $modules->get(0);
        $mod2 = $modules->get(1);
        $mod3 = $modules->get(2);

        $challenges = [
            // =================================================================
            // MÓDULO 1: VARIABLES Y EXPRESIONES
            // =================================================================
            [
                'slug' => 'lab-1-intercambio-variables',
                'module_id' => $mod1?->id,
                'title' => 'Práctica 1.1: Intercambio de Variables con Auxiliar',
                'description' => 'Simula en memoria cómo intercambiar el contenido de dos variables (a y b) usando una variable temporal auxiliar sin perder ningún dato en el proceso.',
                'dimension' => 'variables',
                'difficulty' => 'beginner',
                'xp_reward' => 40,
                'order_index' => 1,
                'simulator_json' => [
                    'code_lines' => [
                        'a = 15',
                        'b = 40',
                        'aux = a',
                        'a = b',
                        'b = aux',
                        'print("Intercambio listo:", a, b)',
                    ],
                    'variables_names' => ['a', 'b', 'aux'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['a' => 15, 'b' => '—', 'aux' => '—'],
                            'changed' => 'a',
                            'output' => null,
                            'explanation' => 'Se reserva el espacio en memoria para la variable "a" y se le asigna el valor entero 15.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['a' => 15, 'b' => 40, 'aux' => '—'],
                            'changed' => 'b',
                            'output' => null,
                            'explanation' => 'Se asigna el valor inicial 40 a la variable "b". Ahora tenemos a = 15 y b = 40.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['a' => 15, 'b' => 40, 'aux' => 15],
                            'changed' => 'aux',
                            'output' => null,
                            'explanation' => '¡Paso crítico! Respaldamos el valor original de "a" (15) dentro de la variable temporal "aux" antes de sobreescribir "a".',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['a' => 40, 'b' => 40, 'aux' => 15],
                            'changed' => 'a',
                            'output' => null,
                            'explanation' => 'Copiamos el valor de "b" (40) dentro de "a". Aunque "a" y "b" valen 40 momentáneamente, el 15 original sigue a salvo en "aux".',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['a' => 40, 'b' => 15, 'aux' => 15],
                            'changed' => 'b',
                            'output' => null,
                            'explanation' => 'Recuperamos el valor guardado en "aux" (15) y lo asignamos a "b". ¡Los valores han quedado intercambiados!',
                        ],
                        [
                            'line' => 6,
                            'vars' => ['a' => 40, 'b' => 15, 'aux' => 15],
                            'changed' => null,
                            'output' => 'Intercambio listo: 40 15',
                            'explanation' => 'La instrucción print() envía a consola los valores finales: a = 40 y b = 15.',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Ordena los bloques lógicos para intercambiar correctamente los valores de las variables x = 80 e y = 25 usando la variable temporal temp.',
                    'blocks' => [
                        ['id' => 'b3', 'code' => 'x = y'],
                        ['id' => 'b1', 'code' => 'x = 80  ;  y = 25'],
                        ['id' => 'b4', 'code' => 'y = temp'],
                        ['id' => 'b2', 'code' => 'temp = x'],
                    ],
                    'trace_question' => 'Al finalizar la prueba de escritorio de los 4 bloques ordenados, ¿qué valores quedan almacenados en "x", en "y" y en "temp" respectivamente?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'x = 25,  y = 80,  temp = 80'],
                        ['id' => 'opt_b', 'label' => 'x = 25,  y = 25,  temp = 80'],
                        ['id' => 'opt_c', 'label' => 'x = 80,  y = 25,  temp = 25'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4'],
                    'correct_trace_option' => 'opt_a',
                    'explanation_success' => '¡Excelente prueba de escritorio! Guardaste primero x en temp (80), pasaste y hacia x (25) y finalmente asignaste temp hacia y (80).',
                    'explanation_error_order' => 'Revisa la secuencia: primero debes inicializar (x = 80 ; y = 25), luego respaldar x en temp (temp = x), después asignar x = y, y por último y = temp.',
                    'explanation_error_trace' => 'Recuerda que temp conserva el valor original de x (80) incluso después de asignarlo a y.',
                ],
            ],
            [
                'slug' => 'lab-2-jerarquia-facturacion',
                'module_id' => $mod1?->id,
                'title' => 'Práctica 1.2: Cálculo de Subtotal y Descuento en Memoria',
                'description' => 'Realiza la traza de memoria de un algoritmo aritmético que calcula el total a pagar por útiles escolares aplicando descuento.',
                'dimension' => 'abstraction',
                'difficulty' => 'beginner',
                'xp_reward' => 40,
                'order_index' => 2,
                'simulator_json' => [
                    'code_lines' => [
                        'precio = 50',
                        'cantidad = 3',
                        'subtotal = precio * cantidad',
                        'descuento = 25',
                        'total = subtotal - descuento',
                        'print("Total a pagar Q:", total)',
                    ],
                    'variables_names' => ['precio', 'cantidad', 'subtotal', 'descuento', 'total'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['precio' => 50, 'cantidad' => '—', 'subtotal' => '—', 'descuento' => '—', 'total' => '—'],
                            'changed' => 'precio',
                            'output' => null,
                            'explanation' => 'Se inicializa la variable "precio" con el valor unitario de 50.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['precio' => 50, 'cantidad' => 3, 'subtotal' => '—', 'descuento' => '—', 'total' => '—'],
                            'changed' => 'cantidad',
                            'output' => null,
                            'explanation' => 'Se almacena la cantidad de artículos (3) en memoria.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['precio' => 50, 'cantidad' => 3, 'subtotal' => 150, 'descuento' => '—', 'total' => '—'],
                            'changed' => 'subtotal',
                            'output' => null,
                            'explanation' => 'Se evalúa precio * cantidad (50 * 3 = 150) y el resultado se guarda en "subtotal".',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['precio' => 50, 'cantidad' => 3, 'subtotal' => 150, 'descuento' => 25, 'total' => '—'],
                            'changed' => 'descuento',
                            'output' => null,
                            'explanation' => 'Se define un cupón de descuento fijo de 25 quetzales.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['precio' => 50, 'cantidad' => 3, 'subtotal' => 150, 'descuento' => 25, 'total' => 125],
                            'changed' => 'total',
                            'output' => null,
                            'explanation' => 'Se resta subtotal - descuento (150 - 25 = 125) y se almacena en "total".',
                        ],
                        [
                            'line' => 6,
                            'vars' => ['precio' => 50, 'cantidad' => 3, 'subtotal' => 150, 'descuento' => 25, 'total' => 125],
                            'changed' => null,
                            'output' => 'Total a pagar Q: 125',
                            'explanation' => 'Se imprime en pantalla el resultado final almacenado en "total".',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Ordena las instrucciones para calcular el puntaje total de un jugador que gana 4 monedas de 20 puntos cada una y luego recibe un bono extra de 15 puntos.',
                    'blocks' => [
                        ['id' => 'b3', 'code' => 'puntaje_final = base + bono'],
                        ['id' => 'b1', 'code' => 'monedas = 4  ;  valor = 20'],
                        ['id' => 'b4', 'code' => 'print(puntaje_final)'],
                        ['id' => 'b2', 'code' => 'base = monedas * valor  ;  bono = 15'],
                    ],
                    'trace_question' => 'Según la prueba de escritorio, ¿qué valores tendrán las variables "base" y "puntaje_final" al terminar la ejecución?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'base = 24,  puntaje_final = 39'],
                        ['id' => 'opt_b', 'label' => 'base = 80,  puntaje_final = 95'],
                        ['id' => 'opt_c', 'label' => 'base = 80,  puntaje_final = 65'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4'],
                    'correct_trace_option' => 'opt_b',
                    'explanation_success' => '¡Correcto! Primero se definen las variables de entrada, luego se calcula base = 4 * 20 (80), después se suma el bono de 15 obteniendo 95, y finalmente se muestra el resultado.',
                    'explanation_error_order' => 'No puedes usar las variables "base" y "bono" antes de haberlas calculado. Primero declara monedas y valor, luego calcula base y bono, suma en puntaje_final y por último imprime.',
                    'explanation_error_trace' => 'Multiplica monedas * valor (4 * 20 = 80) para obtener "base", y luego súmale el bono (80 + 15 = 95).',
                ],
            ],

            // =================================================================
            // MÓDULO 2: ESTRUCTURAS CONDICIONALES
            // =================================================================
            [
                'slug' => 'lab-3-condicional-aprobacion',
                'module_id' => $mod2?->id,
                'title' => 'Práctica 2.1: Ramificación Condicional (if / else)',
                'description' => 'Observa cómo el procesador evalúa una expresión lógica booleana y salta únicamente al bloque verdadero o falso según la nota del estudiante.',
                'dimension' => 'conditionals',
                'difficulty' => 'intermediate',
                'xp_reward' => 40,
                'order_index' => 3,
                'simulator_json' => [
                    'code_lines' => [
                        'nota = 78',
                        'minimo = 70',
                        'if (nota >= minimo):',
                        '    estado = "APROBADO"',
                        'else:',
                        '    estado = "REPROBADO"',
                        'print("Resultado:", estado)',
                    ],
                    'variables_names' => ['nota', 'minimo', 'nota >= minimo', 'estado'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['nota' => 78, 'minimo' => '—', 'nota >= minimo' => '—', 'estado' => '—'],
                            'changed' => 'nota',
                            'output' => null,
                            'explanation' => 'Se guarda la calificación obtenida por el estudiante: nota = 78.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['nota' => 78, 'minimo' => 70, 'nota >= minimo' => '—', 'estado' => '—'],
                            'changed' => 'minimo',
                            'output' => null,
                            'explanation' => 'Se establece el criterio mínimo de aprobación en 70 puntos.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['nota' => 78, 'minimo' => 70, 'nota >= minimo' => 'true', 'estado' => '—'],
                            'changed' => 'nota >= minimo',
                            'output' => null,
                            'explanation' => 'Se evalúa la condición relacional (78 >= 70). Como es Verdadera (true), el flujo entra al bloque del if e ignora el bloque else.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['nota' => 78, 'minimo' => 70, 'nota >= minimo' => 'true', 'estado' => '"APROBADO"'],
                            'changed' => 'estado',
                            'output' => null,
                            'explanation' => 'Se ejecuta la línea interna del if asignando "APROBADO" a la variable estado.',
                        ],
                        [
                            'line' => 7,
                            'vars' => ['nota' => 78, 'minimo' => 70, 'nota >= minimo' => 'true', 'estado' => '"APROBADO"'],
                            'changed' => null,
                            'output' => 'Resultado: APROBADO',
                            'explanation' => 'El programa salta por completo las líneas 5 y 6 (else) y continúa en la línea 7 imprimiendo el resultado.',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Construye la estructura condicional para aplicar un descuento de Q30 si la compra supera o iguala los Q200, o Q0 en caso contrario.',
                    'blocks' => [
                        ['id' => 'b3', 'code' => '    descuento = 30'],
                        ['id' => 'b1', 'code' => 'compra = 160'],
                        ['id' => 'b4', 'code' => 'else:  descuento = 0'],
                        ['id' => 'b2', 'code' => 'if (compra >= 200):'],
                        ['id' => 'b5', 'code' => 'pago_final = compra - descuento'],
                    ],
                    'trace_question' => 'Dado que "compra" inicia en 160, ¿qué camino toma el condicional y cuál es el valor de "pago_final"?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'La condición es true  →  descuento = 30,  pago_final = 130'],
                        ['id' => 'opt_b', 'label' => 'La condición es false →  descuento = 0,   pago_final = 160'],
                        ['id' => 'opt_c', 'label' => 'La condición es false →  descuento = 30,  pago_final = 130'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4', 'b5'],
                    'correct_trace_option' => 'opt_b',
                    'explanation_success' => '¡Dominio total! Estructuraste el bloque if/else en orden jerárquico y determinaste que 160 >= 200 es false, por lo que descuento = 0 y pago_final = 160.',
                    'explanation_error_order' => 'Recuerda la estructura: primero inicializa "compra", luego evalúa "if (compra >= 200):", seguido de la acción verdadera (descuento = 30), luego el "else:" y finalmente el cálculo de "pago_final".',
                    'explanation_error_trace' => 'Observa que compra vale 160. Al evaluar 160 >= 200 el resultado es Falso (false), por lo que salta al else (descuento = 0) y pago_final queda en 160.',
                ],
            ],
            [
                'slug' => 'lab-4-operadores-logicos-beca',
                'module_id' => $mod2?->id,
                'title' => 'Práctica 2.2: Condición Compuesta con Operador AND',
                'description' => 'Analiza en memoria cómo se evalúan dos condiciones simultáneas unidas por el operador lógico AND para otorgar una beca estudiantil.',
                'dimension' => 'decomposition',
                'difficulty' => 'intermediate',
                'xp_reward' => 40,
                'order_index' => 4,
                'simulator_json' => [
                    'code_lines' => [
                        'promedio = 90',
                        'asistencia = 82',
                        'cond_nota = (promedio >= 85)',
                        'cond_asist = (asistencia >= 85)',
                        'if (cond_nota and cond_asist):',
                        '    beca = "OTORGADA"',
                        'else:',
                        '    beca = "DENEGADA"',
                        'print("Estado de beca:", beca)',
                    ],
                    'variables_names' => ['promedio', 'asistencia', 'cond_nota', 'cond_asist', 'beca'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['promedio' => 90, 'asistencia' => '—', 'cond_nota' => '—', 'cond_asist' => '—', 'beca' => '—'],
                            'changed' => 'promedio',
                            'output' => null,
                            'explanation' => 'Se registra el promedio académico del estudiante (90 puntos).',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => '—', 'cond_asist' => '—', 'beca' => '—'],
                            'changed' => 'asistencia',
                            'output' => null,
                            'explanation' => 'Se registra el porcentaje de asistencia del estudiante (82%).',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => 'true', 'cond_asist' => '—', 'beca' => '—'],
                            'changed' => 'cond_nota',
                            'output' => null,
                            'explanation' => 'Primera condición: 90 >= 85 se evalúa como Verdadero (true).',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => 'true', 'cond_asist' => 'false', 'beca' => '—'],
                            'changed' => 'cond_asist',
                            'output' => null,
                            'explanation' => 'Segunda condición: 82 >= 85 se evalúa como Falso (false) porque no alcanzó el 85% requerido.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => 'true', 'cond_asist' => 'false', 'beca' => '—'],
                            'changed' => null,
                            'output' => null,
                            'explanation' => 'El operador AND exige que AMBAS sean true. Como tenemos (true and false), el resultado global es false y salta al bloque else.',
                        ],
                        [
                            'line' => 8,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => 'true', 'cond_asist' => 'false', 'beca' => '"DENEGADA"'],
                            'changed' => 'beca',
                            'output' => null,
                            'explanation' => 'Se ejecuta la rama del else asignando "DENEGADA" a la variable beca.',
                        ],
                        [
                            'line' => 9,
                            'vars' => ['promedio' => 90, 'asistencia' => 82, 'cond_nota' => 'true', 'cond_asist' => 'false', 'beca' => '"DENEGADA"'],
                            'changed' => null,
                            'output' => 'Estado de beca: DENEGADA',
                            'explanation' => 'Se muestra en consola el dictamen final.',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Ordena el algoritmo verificador que permite pasar al nivel avanzado solo si el estudiante tiene nivel >= 3 Y además tiene llave_maestra == true.',
                    'blocks' => [
                        ['id' => 'b2', 'code' => 'if (nivel >= 3 and llave_maestra == true):'],
                        ['id' => 'b4', 'code' => 'else:  acceso = "BLOQUEADO"'],
                        ['id' => 'b1', 'code' => 'nivel = 4  ;  llave_maestra = true'],
                        ['id' => 'b3', 'code' => '    acceso = "PERMITIDO"'],
                    ],
                    'trace_question' => 'Con los valores iniciales nivel = 4 y llave_maestra = true, ¿qué valor booleano produce la expresión del if y qué valor guarda "acceso"?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'true and true → true   |  acceso = "PERMITIDO"'],
                        ['id' => 'opt_b', 'label' => 'true and false → false |  acceso = "BLOQUEADO"'],
                        ['id' => 'opt_c', 'label' => 'false and true → false |  acceso = "BLOQUEADO"'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4'],
                    'correct_trace_option' => 'opt_a',
                    'explanation_success' => '¡Perfecto! Ambas partes de la conjunción lógica (4 >= 3 y llave_maestra == true) se cumplen, dando true y asignando acceso = "PERMITIDO".',
                    'explanation_error_order' => 'Primero debes declarar las variables (nivel = 4 ; llave_maestra = true), luego abrir el condicional if, colocar la instrucción indentada acceso = "PERMITIDO" y cerrar con el bloque else.',
                    'explanation_error_trace' => 'Como nivel es 4 (que sí es >= 3) y llave_maestra es true, ambas condiciones son verdaderas: true and true produce true.',
                ],
            ],

            // =================================================================
            // MÓDULO 3: ESTRUCTURAS CÍCLICAS (BUCLES)
            // =================================================================
            [
                'slug' => 'lab-5-bucle-while-acumulador',
                'module_id' => $mod3?->id,
                'title' => 'Práctica 3.1: Traza de Contador y Acumulador en Bucle while',
                'description' => 'Inspecciona iteración por iteración cómo un bucle while actualiza un contador y suma valores dentro de un acumulador hasta que la condición se vuelve falsa.',
                'dimension' => 'loops',
                'difficulty' => 'advanced',
                'xp_reward' => 40,
                'order_index' => 5,
                'simulator_json' => [
                    'code_lines' => [
                        'suma = 0',
                        'i = 1',
                        'while (i <= 3):',
                        '    suma = suma + (i * 10)',
                        '    i = i + 1',
                        'print("Suma acumulada:", suma)',
                    ],
                    'variables_names' => ['i', 'i <= 3', 'suma'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['i' => '—', 'i <= 3' => '—', 'suma' => 0],
                            'changed' => 'suma',
                            'output' => null,
                            'explanation' => 'Se inicializa el acumulador "suma" en 0 antes de comenzar el ciclo.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['i' => 1, 'i <= 3' => '—', 'suma' => 0],
                            'changed' => 'i',
                            'output' => null,
                            'explanation' => 'Se inicializa la variable de control (contador) "i" en 1.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['i' => 1, 'i <= 3' => 'true', 'suma' => 0],
                            'changed' => 'i <= 3',
                            'output' => null,
                            'explanation' => 'Iteración 1: Se evalúa (1 <= 3). Es true, por lo que entra al cuerpo del bucle.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['i' => 1, 'i <= 3' => 'true', 'suma' => 10],
                            'changed' => 'suma',
                            'output' => null,
                            'explanation' => 'suma = 0 + (1 * 10) → El acumulador "suma" pasa a valer 10.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['i' => 2, 'i <= 3' => 'true', 'suma' => 10],
                            'changed' => 'i',
                            'output' => null,
                            'explanation' => 'Se incrementa el contador: i = 1 + 1 = 2.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['i' => 2, 'i <= 3' => 'true', 'suma' => 10],
                            'changed' => 'i <= 3',
                            'output' => null,
                            'explanation' => 'Iteración 2: Se evalúa (2 <= 3). Sigue siendo true, repite el ciclo.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['i' => 2, 'i <= 3' => 'true', 'suma' => 30],
                            'changed' => 'suma',
                            'output' => null,
                            'explanation' => 'suma = 10 + (2 * 10) → El acumulador "suma" sube a 30.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['i' => 3, 'i <= 3' => 'true', 'suma' => 30],
                            'changed' => 'i',
                            'output' => null,
                            'explanation' => 'Se incrementa el contador: i = 2 + 1 = 3.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['i' => 3, 'i <= 3' => 'true', 'suma' => 30],
                            'changed' => 'i <= 3',
                            'output' => null,
                            'explanation' => 'Iteración 3: Se evalúa (3 <= 3). Es true por última vez.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['i' => 3, 'i <= 3' => 'true', 'suma' => 60],
                            'changed' => 'suma',
                            'output' => null,
                            'explanation' => 'suma = 30 + (3 * 10) → El acumulador "suma" alcanza 60.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['i' => 4, 'i <= 3' => 'true', 'suma' => 60],
                            'changed' => 'i',
                            'output' => null,
                            'explanation' => 'Se incrementa el contador: i = 3 + 1 = 4.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['i' => 4, 'i <= 3' => 'false', 'suma' => 60],
                            'changed' => 'i <= 3',
                            'output' => null,
                            'explanation' => 'Se evalúa (4 <= 3). Ahora es Falso (false), por lo que el bucle while termina y salta a la línea 6.',
                        ],
                        [
                            'line' => 6,
                            'vars' => ['i' => 4, 'i <= 3' => 'false', 'suma' => 60],
                            'changed' => null,
                            'output' => 'Suma acumulada: 60',
                            'explanation' => 'Se imprime el total acumulado tras las 3 iteraciones: 60.',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Ordena los bloques para construir un bucle while que sume los números del 1 al 3 dentro de la variable "total", evitando caer en un ciclo infinito.',
                    'blocks' => [
                        ['id' => 'b4', 'code' => '    k = k + 1'],
                        ['id' => 'b2', 'code' => 'while (k <= 3):'],
                        ['id' => 'b1', 'code' => 'total = 0  ;  k = 1'],
                        ['id' => 'b5', 'code' => 'print(total)'],
                        ['id' => 'b3', 'code' => '    total = total + k'],
                    ],
                    'trace_question' => 'Al terminar el bucle y salir a la línea print(total), ¿qué valores exactos tienen "total" y el contador "k" en memoria?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'total = 6,   k = 3'],
                        ['id' => 'opt_b', 'label' => 'total = 6,   k = 4'],
                        ['id' => 'opt_c', 'label' => 'total = 10,  k = 4'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4', 'b5'],
                    'correct_trace_option' => 'opt_b',
                    'explanation_success' => '¡Brillante! Sumaste 1 + 2 + 3 = 6 antes de incrementar k en cada vuelta, y notaste que el bucle se detiene justo cuando k alcanza el valor 4 (porque 4 <= 3 es falso).',
                    'explanation_error_order' => 'Primero inicializa (total = 0 ; k = 1), luego abre "while (k <= 3):", dentro suma primero "total = total + k", después incrementa "k = k + 1", y fuera del ciclo imprime "print(total)".',
                    'explanation_error_trace' => '¡Cuidado con el valor final del contador! En la tercera vuelta k vale 3, suma total = 6 y luego ejecuta k = 3 + 1 = 4. Es con k = 4 que la condición (k <= 3) se vuelve falsa.',
                ],
            ],
            [
                'slug' => 'lab-6-bucle-for-pares',
                'module_id' => $mod3?->id,
                'title' => 'Práctica 3.2: Filtrado con Bucle for y Condicional Anidado',
                'description' => 'Combina un ciclo repetitivo for con una estructura condicional if usando el operador módulo (%) para contar cuántos números pares hay en una secuencia.',
                'dimension' => 'algorithms',
                'difficulty' => 'advanced',
                'xp_reward' => 40,
                'order_index' => 6,
                'simulator_json' => [
                    'code_lines' => [
                        'pares = 0',
                        'for num in [1, 2, 3, 4]:',
                        '    if (num % 2 == 0):',
                        '        pares = pares + 1',
                        'print("Cantidad de pares:", pares)',
                    ],
                    'variables_names' => ['num', 'num % 2 == 0', 'pares'],
                    'steps' => [
                        [
                            'line' => 1,
                            'vars' => ['num' => '—', 'num % 2 == 0' => '—', 'pares' => 0],
                            'changed' => 'pares',
                            'output' => null,
                            'explanation' => 'Se inicializa el contador de números pares en cero.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['num' => 1, 'num % 2 == 0' => '—', 'pares' => 0],
                            'changed' => 'num',
                            'output' => null,
                            'explanation' => 'Iteración 1 del for: la variable "num" toma el primer valor de la lista (1).',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['num' => 1, 'num % 2 == 0' => 'false', 'pares' => 0],
                            'changed' => 'num % 2 == 0',
                            'output' => null,
                            'explanation' => '1 % 2 da residuo 1 (no es 0). Como es impar (false), no incrementa "pares".',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['num' => 2, 'num % 2 == 0' => 'false', 'pares' => 0],
                            'changed' => 'num',
                            'output' => null,
                            'explanation' => 'Iteración 2 del for: "num" toma el segundo valor de la lista (2).',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['num' => 2, 'num % 2 == 0' => 'true', 'pares' => 0],
                            'changed' => 'num % 2 == 0',
                            'output' => null,
                            'explanation' => '2 % 2 da residuo 0 (true). Entra al condicional if.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['num' => 2, 'num % 2 == 0' => 'true', 'pares' => 1],
                            'changed' => 'pares',
                            'output' => null,
                            'explanation' => 'Se incrementa el contador: pares = 0 + 1 = 1.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['num' => 3, 'num % 2 == 0' => 'true', 'pares' => 1],
                            'changed' => 'num',
                            'output' => null,
                            'explanation' => 'Iteración 3 del for: "num" toma el valor 3.',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['num' => 3, 'num % 2 == 0' => 'false', 'pares' => 1],
                            'changed' => 'num % 2 == 0',
                            'output' => null,
                            'explanation' => '3 % 2 da residuo 1 (false). Salta a la siguiente vuelta del bucle.',
                        ],
                        [
                            'line' => 2,
                            'vars' => ['num' => 4, 'num % 2 == 0' => 'false', 'pares' => 1],
                            'changed' => 'num',
                            'output' => null,
                            'explanation' => 'Iteración 4 del for: "num" toma el último valor de la lista (4).',
                        ],
                        [
                            'line' => 3,
                            'vars' => ['num' => 4, 'num % 2 == 0' => 'true', 'pares' => 1],
                            'changed' => 'num % 2 == 0',
                            'output' => null,
                            'explanation' => '4 % 2 da residuo 0 (true). Entra al bloque del if.',
                        ],
                        [
                            'line' => 4,
                            'vars' => ['num' => 4, 'num % 2 == 0' => 'true', 'pares' => 2],
                            'changed' => 'pares',
                            'output' => null,
                            'explanation' => 'Se incrementa el contador: pares = 1 + 1 = 2.',
                        ],
                        [
                            'line' => 5,
                            'vars' => ['num' => 4, 'num % 2 == 0' => 'true', 'pares' => 2],
                            'changed' => null,
                            'output' => 'Cantidad de pares: 2',
                            'explanation' => 'Finaliza el recorrido de la lista y muestra el total de números pares encontrados (2).',
                        ],
                    ],
                ],
                'challenge_json' => [
                    'prompt' => 'Ordena el algoritmo para recorrer la lista de precios [15, 60, 25, 80] y acumular en "suma_mayores" únicamente aquellos precios que sean mayores o iguales a 50.',
                    'blocks' => [
                        ['id' => 'b3', 'code' => '    if (p >= 50):'],
                        ['id' => 'b1', 'code' => 'suma_mayores = 0'],
                        ['id' => 'b5', 'code' => 'print(suma_mayores)'],
                        ['id' => 'b2', 'code' => 'for p in [15, 60, 25, 80]:'],
                        ['id' => 'b4', 'code' => '        suma_mayores = suma_mayores + p'],
                    ],
                    'trace_question' => 'Al concluir las 4 iteraciones del ciclo for, ¿qué valor final queda guardado en la variable "suma_mayores"?',
                    'trace_options' => [
                        ['id' => 'opt_a', 'label' => 'suma_mayores = 180  (suma todos los elementos)'],
                        ['id' => 'opt_b', 'label' => 'suma_mayores = 140  (solo suma 60 + 80)'],
                        ['id' => 'opt_c', 'label' => 'suma_mayores = 2    (cuenta cuántos cumplen)'],
                    ],
                ],
                'solution_json' => [
                    'correct_block_order' => ['b1', 'b2', 'b3', 'b4', 'b5'],
                    'correct_trace_option' => 'opt_b',
                    'explanation_success' => '¡Excelente dominio de ciclos anidados! Inicializaste el acumulador, recorriste cada precio con for, filtraste con if (p >= 50) y sumaste 60 + 80 = 140.',
                    'explanation_error_order' => 'El orden correcto es: 1) inicializar suma_mayores = 0, 2) abrir el ciclo for, 3) evaluar el filtro if (p >= 50):, 4) acumular suma_mayores = suma_mayores + p, y 5) imprimir al final.',
                    'explanation_error_trace' => 'Solo los valores 60 y 80 cumplen la condición (p >= 50). Como la línea suma "+ p", acumula sus valores: 60 + 80 = 140.',
                ],
            ],
        ];

        foreach ($challenges as $ch) {
            LabChallenge::query()->updateOrCreate(
                ['slug' => $ch['slug']],
                $ch
            );
        }
    }
}
