# EduGT — Plan de Arquitectura Backend (Modular SOLID)

> **For Hermes:** Usar el skill `subagent-driven-development` para implementar este plan tarea por tarea.

**Versión:** 1.0 — 2026-09-29
**Responsable:** IA de Backend (este documento NO define frontend)
**Alcance:** Laravel (PHP 8.2+) + MySQL 8 sobre Docker, monolito modular con capas de dominio, API REST para un frontend separado y desacoplado.

---

## 1. Contexto, supuestos y decisiones de alcance

### 1.1 Estado del entorno

| Aspecto | Estado |
|---|---|
| Carpeta del proyecto | `C:\Users\Jose\Desktop\EduGT2` — **vacía**, se inicia de 0 |
| Fuente de requisitos | Documento de tesis adjunto (RF-1..RF-28 y reglas RB below) |
| Stack backend | Laravel + Sanctum + Socialite + Eloquent + MySQL 8 + Docker Compose |
| Docker | Se asume Docker Desktop instalado; Fase 0 lo verifica |

### 1.2 Decisiones arquitectónicas

1. **Monolito modular** (no microservicios). Un solo Laravel, dominio separado en módulos con dependencias unidireccionales entre módulos. Suficiente para n≥108 piloto y 50 concurrentes.
2. **Server-authoritative:** toda la lógica de gamificación, evaluación e inscripción se calcula y valida en el backend vía `source of truth = servidor` en RB-10. El cliente nunca envía puntajes ni desbloqueos.
3. **Separación de los dos conceptos "nivel":** la ruta de aprendizaje (pretest) y el nivel XP de gamificación son dos columnas y dos servicios distintos (§3.1.3). La tesis mezcla ambas palabras; separarlas evita la contaminación de la gamificación por resultados de diagnóstico — exactamente lo que la tesis exige: el pretest queda fuera de la recompensa.
4. **Idioma del código:** identificadores, tablas y endpoints en inglés de Laravel; `user-facing messages` de error en español vía `lang/es`.
5. **El esquema de BD es el contrato:** congelado en Fase 1; el único agente que escribe migraciones/DDL es backend.
6. **PDF-only:** reportes únicamente vía Dompdf; sin Excel ni más formatos.

### 1.3 Riesgos globales

- Fatiga/abandono en pretest/postest → mitigado en Fase 5 con piloto y hard-cap de 20 preguntas.
- Doble significado de "nivel" → mapeado explícito en §3.1.3.
- Estados offline → la recompensa se pospone ("reward queue"), no se pre-otorga.
- Email duplicado por Google/tradicional → decisión explícita documentada (RB-03), no un bug silencioso.

---

## 2. Módulos de dominio y su responsabilidad única (SRP)

| Módulo | Responsabilidad única | No hace |
|---|---|---|
| `Auth` | Registro tradicional, login, OAuth Google, aprobación de docentes | Gamificación |
| `Users` | Roles, estados de cuenta, perfiles | Autenticación |
| `Curriculum` | Misión→Módulo→Lección, publicación, bloqueo/desbloqueo, progreso | Evaluación |
| `Diagnostic` | Pretest/Postest, dimensiones, comparabilidad | Gamificación (explícitamente excluido) |
| `Assessments` | Quizzes Card-Flow, intentos, grading en servidor | XP (delega a Gamification) |
| `Gamification` | XP, niveles, racha, badges, ranking | Evaluación |
| `Classrooms` | Aulas, códigos de inscripción, membresías | Analytics |
| `Analytics` | Métricas, comparativas pre/post, riesgo académico | Exportar PDF (lo hace Reporting) |
| `Reporting` | Exportación PDF exclusivamente | Cálculo de métricas (importa Analytics) |
| `Audit` | Bitácora de acciones admin y accesos a datos grupales | Analytics |
| `Support` | Reportes de errores/sugerencias (docente) | Gamificación |
| `OfflineSync` | Bundle descargable y cola de sincronización con revalidación | Otorgar XP offline |

Regla de dependencia: `Curriculum` y `Assessments` pueden llamar a `Gamification` **solo a través de sus interfaces** (`XpLedger`, `BadgeAwarder`, `RankUpdater`), no al revés.

### 2.1 Mapeo RF del documento de tesis → módulo backend y fase

| RF | Requisito (resumen) | Módulo | Fase |
|---|---|---|---|
| RF-1 | Autenticación tradicional | Auth | 2 |
| RF-2 | Login con Google | Auth | 2 |
| RF-3 | Registro solo estudiante/docente | Auth (RB-01) | 2 |
| RF-4 | Aprobación administrativa de docentes | Auth + Admin | 3 |
| RF-5 | Prevención de duplicados por email | Auth (RB-03/04) | 2 |
| RF-6 | Pretest diagnóstico | Diagnostic | 5 |
| RF-7 | Nivel inicial automático | Diagnostic + Users | 5 |
| RF-8 | Estructura misión/módulo/lección | Curriculum | 4 |
| RF-9 | Lecciones teoría/Markdown/pseudocódigo/retos | Curriculum | 4 |
| RF-10 | Progreso y tiempo invertido | Curriculum | 4 |
| RF-11 | Cuestionarios Card-Flow | Assessments | 7 |
| RF-12 | Calificación en servidor | Assessments (QuizGrader) | 7 |
| RF-13 | Retroalimentación explicativa inmediata | Assessments (feedback por opción) | 7 |
| RF-14 | Historial de intentos | Assessments (quiz_attempts) | 7 |
| RF-15 | Aprobación mínima 70/100 | RB-05 (UnlockEvaluator) | 7 |
| RF-16 | Reintentos ilimitados, XP −20 % adicional | RB-06 (XpLedger) | 7 |
| RF-17 | XP, niveles, racha, insignias | Gamification | 8 |
| RF-18 | Ranking global y por aula | Gamification (RankUpdater) | 8 |
| RF-19 | Aulas y códigos de inscripción | Classrooms | 9 |
| RF-20 | Monitoreo docente individual y grupal | Analytics + Classrooms | 10 |
| RF-21 | Métricas (aprobación, dificultad, tiempo, riesgo) | Analytics | 10 |
| RF-22 | Reportes exclusivamente PDF | Reporting | 10 |
| RF-23 | Bitácora de actividad y auditoría | Audit | 11 |
| RF-24 | Reporte de errores y sugerencias | Support | 11 |
| RF-25 | Modo offline parcial (lecturas) | OfflineSync | 12 |
| RF-26 | No XP ni intentos oficiales offline | RB-13 (SyncValidator) | 12 |
| RF-27 | Sincronización al volver la conexión | OfflineSync | 12 |
| RF-28 | Pretest y postest para evaluación (Wilcoxon) | Diagnostic | 5 y 13 |

---

## 3. Modelo de dominio

### 3.1 Users / Auth

- `users`: `id, name, email UNIQUE, password NULLABLE, role ENUM('student','teacher','admin'), status ENUM('active','pending_approval','suspended'), learning_track ENUM('beginner','intermediate','advanced') NULL, google_id NULL UNIQUE, avatar_url NULL, timestamps`
- El primer admin se crea con un comando artisan dedicado (`admin:create`) o seed, **nunca** vía endpoint público ni por registro.

#### 3.1.1 Reglas de negocio del rol

1. El rol `admin` **nunca** se recibe desde el cliente.
2. Un docente nace `pending_approval`; lo activa un admin (Fase 3).
3. `role` y `status` NO están en `$fillable` de formularios públicos; solo cambian en endpoints admin con Policy.
4. Docente `pending_approval` no puede crear aulas ni ver analítica (Policy devuelve 403).

#### 3.1.2 OAuth Google (Socialite)

- `GoogleOAuthGateway` (interfaz) + `SocialiteGoogleGateway` (implementación).
- Si el usuario es nuevo → devolver `RequireRoleChoice(name, email)`; solo student/teacher (RB-01). Student se activa; teacher queda `pending_approval`.
- Si el email ya existe → login de esa cuenta, sin crear duplicados (RB-03) y **sin cambiar su rol** (RB-04).
- Si el email existe como cuenta tradicional (sin `google_id`) → vincular con password una sola vez (reconciliación anti-duplicado).
- Validación del id_token en backend; no se almacenan perfiles/tokens de Google salvo `google_id` y `avatar_url`.

#### 3.1.3 Los dos significados de "nivel" — mapa explícito

| Concepto de la tesis | Campo técnico | Quién lo asigna |
|---|---|---|
| Ruta de aprendizaje (nivel inicial del pretest) | `users.learning_track` | `DiagnosticLevelAssigner` al cerrar el pretest |
| Nivel de gamificación (XP) | `user_xp_profile.xp_level` (int) | `XpLedger` al otorgar XP |

Esto es una decisión de diseño, no un cambio de requisitos: la API siempre nombra los dos conceptos de forma distinta para que el FE no los mezcle.

### 3.2 Gamification

- `xp_ledger` append-only: `user_id, delta, source ENUM('quiz','lesson','streak','manual'), reason, metadata JSON, created_at`. El balance se deriva de la suma (o se materializa en `user_xp_profile.xp_total`); nunca se borra ni edita.
- `user_xp_profile` (1:1 user): `xp_total, xp_level, streak_current, streak_longest, streak_last_active_date`.
- Barra de nivel: `nivel N requiere XP acumulado >= 100*N` (esto solo define cómo se muestra la barra; no puede ocurrir que una misma fuente sume doble y si una regla dijera "restar" se registra `delta` negativo).
- `badges` (catálogo: las 6 del documento, con `deleted_at` para retirar de catálogo sin romper historial) y `user_badges` con `UNIQUE(user_id, badge_id)` (RB-07).
- `badge_rules`: reglas declarativas en BD (condición + evento disparador + badge), seed inicial. La regla en BD hace la asignación auditable y manageable por el admin sin deploy (decisión: BD, no código).

### 3.3 Curriculum

- `missions`: `slug, title, description, xp_reward, order_index, status ENUM('draft','published')`
- `modules`: FK mission, `title, required_level (int, puede ser 0), order_index, status`
- `lessons`: FK module, `title, content_markdown, pseudocode_json JSON, estimated_minutes, xp_reward, order_index, requires_quiz_pass BOOL, status`
- `lesson_progress` UNIQUE(user_id, lesson_id): `started_at, last_visited_at, time_spent_seconds, completed BOOL, completed_at`
- Desbloqueo (RB-05): para ver la lección N+1 del módulo, la N debe estar `completed`; un module exige `required_level <= xp_level` y que el usuario haya completado sus lecciones con `requires_quiz_pass=1` aprobadas con ≥70.
- La regla se evalúa con `UnlockEvaluator` (interfaz); el frontend la consulta siempre (en `GET /missions` cada lección lleva `locked: bool`), pero el backend devuelve 403 si intentan saltarla.

### 3.4 Assessments

- `quizzes`: FK module, `passing_score DEFAULT 70`
- `questions`: FK quiz, `type ('single','multiple','true_false'), explanation TEXT (feedback de pregunta), points` y `question_options` con `is_correct BOOL, feedback TEXT` (feedback por opción: se entrega al responder — RF-13)
- `quiz_attempts`: `user_id, quiz_id, attempt_number (ordinal por user+quiz), started_at, submitted_at NULL, score 0-100 NULL, status ENUM('in_progress','submitted','abandoned'), xp_awarded BOOL DEFAULT false, xp_amount INT NULL`
- `attempt_answers`: FK attempt/question/option, `is_correct, created_at` — historial completo para analytics (RF-21).
- El ordinal `attempt_number` es base del cálculo de XP en reintentos (RB-06/15); se determina en servidor en el momento de crear el intento.

### 3.5 Diagnostic (pretest y postest)

- `diagnostic_tests`: `kind ENUM('pre','post'), version, question_count INT (12..20), time_limit_minutes DEFAULT 15`
- `diagnostic_questions`: FK test, `dimension ENUM('abstraction','decomposition','pattern_recognition','algorithms','variables','conditionals','loops'), options JSON + correct_index, explanation TEXT`
- `diagnostic_answers`: FK attempt/question, `selected_option, is_correct` — se guardan una a una para permitir reanudar tras interrupción (RF-28).
- `diagnostic_attempts`: `user_id, test_id, dimension_scores JSON, score_total, started_at, submitted_at NULL, status ENUM('in_progress','completed','abandoned')`
- Reglas: 12-15 preguntas recomendadas, ≤20 hard-cap; una sola presentación de cada test por usuario (RF-6, "solo UNA vez"); el POST `/tests/{kind}/submit` calcula `dimension_scores` y `score_total` en servidor (calificación en servidor, RF-12); **0 XP** (RF-26/RB-11); el postest solo se desbloquea cuando el admin/docente lo habilita.
- Comparabilidad (RF-28): el postest comparte el mismo modelo de dimensiones y conteo por dimensión, pero con banco de preguntas equivalentes (comparación Wilcoxon n≥108).
- La asignación de ruta usa `DiagnosticLevelAssigner`, que establece `learning_track` y no otorga XP, insignias ni toca ranking. El test de arquitectura verifica que `Diagnostic` no importa `Gamification`.

### 3.6 Classrooms

- `classrooms`: `name, institution, grade_level, academic_year, teacher_id FK, is_active BOOL, timestamps`
- `classroom_codes`: `classroom_id, code UNIQUE, is_active, revoked_at NULL` — el docente puede revocar y regenerar; un código inactivo no inscribe a nadie.
- `classroom_students`: pivot `classroom_id, student_id, joined_at, state ENUM('active','archived','removed')` UNIQUE(classroom_id, student_id).

### 3.7 Analytics / Reporting

- `Analytics` NO tiene tablas propias de métricas: calcula con **consultas SQL agregadas** sobre `quiz_attempts`, `attempt_answers`, `lesson_progress`, `diagnostic_attempts` para obtener: promedio general, tasa de aprobación, módulos con mayor dificultad, preguntas con mayor % de error, tiempo promedio de resolución, intentos por estudiante, retención, estudiantes en riesgo de rezago, progresos individuales y grupales (todos RF-21).
- Definición de **riesgo académico** (regla aquí, no en FE): estudiantes con `tasa_aprobacion < 70%` e `intentos_promedio > 3` en al menos un módulo.
- `Reporting` exporta PDF exclusivamente (barryvdh/laravel-dompdf); este módulo es el único con permiso de conocer ese paquete (interfaz `ReportExporter`, RB-12: ningún endpoint Excel/XLSX).
- Comparaciones pre/post (RF-28, Fase 13): consultas directas comparando `diagnostic_attempts` por `kind` pre vs post sobre la misma cohorte.

### 3.8 Audit / Support / OfflineSync

- `audit_logs`: `user_id, action, target_type, target_id, context JSON, ip, user_agent, created_at` (RF-23) — escribe en acciones admin y accesos de docentes a datos de grupo (RB-09 evidenciado).
- `support_tickets`: `reporter_id, kind ENUM('error','suggestion'), description, module REF NULL, status ENUM('open','in_review','resolved'), timestamps` (RF-24)
- `OfflineSync` (RF-25..27): `GET /offline/bundle` manda un JSON con lecciones (`content_markdown`, `pseudocode_json`, imágenes) con hash de versión. Cuando vuelve la conexión el cliente hace `POST /offline/sync` con la lista de lecciones leídas y su tiempo; el backend valida que seas el dueño y solo marca progreso de lectura (RB-14, sin XP); los cuestionarios offline NO existen: el quiz no descarga respuestas y el post de un intento necesita el quiz abierto en servidor (RB-13). Idempotencia: merge por `last_visited_at` mayor.

---

## 4. Interfaces entre módulos (DIP)

PHP 8.2; cada módulo expone interfaces mínimas que otros módulos resuelven vía contenedor de Laravel:

```php
interface QuizGrader { public function grade(QuizAttempt $attempt): GradeResult; }

interface XpLedger {
    public function award(int $userId, int $delta, XpSource $source, string $reason, ?array $meta = null): void;
    public function currentTotal(int $userId): int;
}

interface BadgeAwarder { public function evaluate(BadgeTriggerEvent $event): void; }

interface RankUpdater { public function recalculate(RankingScope $scope): void; }

interface DiagnosticLevelAssigner { public function assignFrom(DiagnosticAttempt $attempt): string; }

interface ReportExporter { public function pdf(ReportPayload $payload): StoredReport; }

interface GoogleOAuthGateway { public function resolve(string $idToken): OAuthOutcome; }

interface UnlockEvaluator { public function canAccess(User $user, Lesson $lesson): UnlockDecision; }
```

Dependencias permitidas (solo hacia interfaces del módulo destino):

```
Http/Controllers/Api/V1 -> Domain/{Auth, Curriculum, Assessment, Diagnostic, Gamification(query), Classrooms, Analytics, Reporting, Support, OfflineSync}
Curriculum  -> Gamification(XpLedger, UnlockEvaluator)
Assessments -> Gamification(XpLedger, BadgeAwarder, RankUpdater)
Gamification -> (solo de sí mismo, no dependencia externa)
Diagnostic  -> Users(DiagnosticLevelAssigner)   [jamás toca Gamification]
Reporting   -> Analytics (read-only)
Audit       -> escucha eventos de acciones admin
```

## 5. Estructura de directorios

```
EduGT2/
├── docs/plans/                   <- este documento
├── backend/
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Auth/            (Actions, Services, Policies, Data)
│   │   │   ├── Users/
│   │   │   ├── Curriculum/
│   │   │   ├── Diagnostic/
│   │   │   ├── Assessments/
│   │   │   ├── Gamification/
│   │   │   ├── Classrooms/
│   │   │   ├── Analytics/
│   │   │   ├── Reporting/
│   │   │   ├── Audit/
│   │   │   ├── Support/
│   │   │   └── OfflineSync/
│   │   ├── Http/Controllers/Api/V1/    <- controladores delgados; lógica en Domain
│   │   ├── Policies/
│   │   ├── Support/Shared/             <- helpers sin reglas de negocio
│   │   └── Console/Commands/           <- admin:create,reports:generate,etc.
│   ├── database/migrations/            <- ÚNICO lugar autorizado para DDL
│   ├── tests/{Unit,Feature,Acceptance}
│   └── docker-compose.yml              <- php-fpm + nginx + mysql:8 + redis(rate-limit)
└── AGENTS.md                           <- reglas compartidas entre backend y frontend
```

Cada dominio tiene: `/Actions` (casos de uso), `/Services` (reglas complejas), `Models/` (Eloquent), `/Policies`, `/Data` (DTOs). Las tablas se nombran en snake del dominio, prefijadas solo si es necesario para evitar colisión.

---

## 6. Reglas de negocio — matriz implementable (IDs compartidos con el frontend)

| ID | Enunciado backend (obligatorio, no negociable) | Se cumple en |
|---|---|---|
| RB-01 | `role=` NUNCA se acepta del request; solo 'student' / 'teacher' en registro y oauth/complete | `RegisterAction`, `OAuthCompleteAction` |
| RB-02 | Docente nace `pending_approval`; lo aprueba un admin | `TeacherApprovalService` |
| RB-03 | Email existente en Google → login de esa cuenta, sin duplicar | `OAuthLoginAction` (upsert por email) |
| RB-04 | El rol de la cuenta existente nunca cambia cuando Google llega con otro rol | `OAuthLoginAction` |
| RB-05 | Desbloquear módulo = lessons completed + quiz ≥ 70 y el nivel XP que pida (`required_level`) | `UnlockEvaluator` |
| RB-06 | Reintentos ilimitados; intento n≥2 otorga `floor(base × 0.8^(n-1))`; ordinal `attempt_number` en servidor | `XpLedger.award`, `QuizAttemptService` |
| RB-07 | Insignias únicas por usuario | `user_badges` UNIQUE |
| RB-08 | Ranking se recalcula SOLO tras quiz validado en servidor (no pretest/postest/lecciones) | `RankUpdater` |
| RB-09 | Estudiante ve solo su progreso; teacher solo de aulas donde sea dueño; admin todo | Policies por módulo |
| RB-10 | El navegador NO es fuente de verdad; todo se recalcula en servidor en el submit | `QuizGrader` |
| RB-11 | Pretest/postest no otorgan XP, insignias ni afectan ranking | `Diagnostic` no importa `Gamification` |
| RB-12 | Reportes únicamente PDF; sin endpoints Excel/XLSX | `ReportExporter` (Dompdf) |
| RB-13 | Offline permite leer lecciones, pero un cuestionario offline NO registra intento oficial ni XP al sincronizar | `OfflineSync.SyncValidator` |
| RB-14 | S Incronizar progreso de lectura al volver conexión: solo time/last_visited, sin XP | `OfflineSync.SyncController` |
| RB-15 | La reducción de XP en reintentos es sobre la recompensa base, no sobre XP ya otorgados (intento 1 `base`, intento 2 `0.8×base`, intento 3 `0.64×base`) | `XpLedger.award` |
| RB-16 | Racha por día local `America/Guatemala`: actividad del día (login o completar lección/quiz) cuenta 1; 0 actividad rompe la racha | `StreakService` |
| RB-17 | El postest comparte la MISMA estructura de dimensiones y conteo del pretest | seeds de `diagnostic_tests` por versión |

---

## 7. Contrato API REST v1

Base `/api/v1`, Sanctum Bearer. Markdown de respuesta de error común: `{message, errors?, code, status}` — mensaje en español.

### Auth

| Método | Ruta | Request | Respuesta | Reglas |
|---|---|---|---|---|
| POST | `/register` | `name, email, password, role: student\|teacher` | 201 `{user}` | RB-01, RB-02 |
| POST | `/login` | `email, password` | 200 `{user, token}` · 423 si teacher pendiente (`TEACHER_PENDING_APPROVAL`) · 429 rate-limited 5/min por email+IP | RB-02 |
| GET | `/google/redirect` | — | 302 → Google (state anti-CSRF) | — |
| GET | `/google/callback` | — | 200 `{needs_role_choice, name, email, temp_token}` | RB-03, RB-04 |
| POST | `/google/complete` | `name, role: student\|teacher` (con `temp_token`) | 201/200 `{user, token}` | RB-01 |
| POST | `/logout` | — | 204 | — |

### Curriculum

- `GET /me/missions` — árbol con `locked/unlocked/completed` de cada lección
- `GET /lessons/{id}` — contenido Markdown, pseudocódigo, tiempo estimado (403 si `locked`)
- `POST /lessons/{id}/start` · `POST /lessons/{id}/complete` `{time_spent_seconds}`
- `GET /me/progress` — % por módulo, lecciones completadas, última actividad

### Diagnostic

- `GET /diagnostic/tests/{kind}` — `kind=pre\|post`; 409 `DIAGNOSTIC_ALREADY_COMPLETED` si ya lo tomó
- `POST /diagnostic/tests/{kind}/answers` — 1 pregunta por POST: `{question_id, option}` (permite guardar avance y reanudar)
- `POST /diagnostic/tests/{kind}/submit` — cierra intento; devuelve `{score_total, dimension_scores}` y aplica `DiagnosticLevelAssigner` si `kind=pre`

### Assessments

- `GET /quizzes/{id}` — preguntas SIN marcar correctas (RB-10); 403 si quiz locked
- `POST /quizzes/{id}/attempts` — crea intento con `attempt_number` ordinal en servidor
- `POST /quizzes/{id}/attempts/{aid}/answers` — 1 respuesta, devuelve `{is_correct, feedback}` (RF-13)
- `POST /quizzes/{id}/attempts/{aid}/submit` — cierra y gradea en servidor; aplica RB-05/06/07/08

### Gamification / Rankings

- `GET /me/xp` · `GET /me/streak` · `GET /me/badges` (catálogo: blocked/available/obtained)
- `GET /rankings?scope=global|classroom&classroom_id=` — paginado por cursor

### Classrooms

- `POST /classrooms` (teacher activo) · `GET /classrooms/{id}/students` · `POST /classrooms/{id}/codes/regenerate`
- `POST /classrooms/join` `{code}` (student) — valida código activo, no duplica membresía
- `GET /classrooms/{id}/analytics` (teacher dueño / admin)

### Reporting (teacher/admin)

- `POST /reports` `{kind, filters}` → 202 `{report_id}` (job en cola)
- `GET /reports/{id}` → `{status, download_url}` · `GET /reports/{id}/download` → PDF
- Sin endpoints Excel/XLSX (RB-12)

### Admin

- `GET /admin/teachers?status=pending` · `POST /admin/teachers/{id}/approve` · `POST /admin/teachers/{id}/reject`
- CRUD missions/modules/lessons/quizzes/badges/badge_rules + contenido en borrador/publicado
- Habilitar postest por aula · `GET /admin/audit-logs`

---

## 8. Fases de desarrollo y criterios de aceptación

| Fase | Entregable | Criterio de aceptación verificable |
|---|---|---|
| 0. Infra | `docker-compose.yml` levanta php-fpm+nginx+mysql | `docker compose up -d` y `curl localhost:8080/api/v1/health` → 200 |
| 1. Schema | Migraciones de §3 + seeds mínimos | `php artisan migrate:fresh` OK; el diagrama ER coincide con §3 |
| 2. Auth | Registro + login + Google OAuth | Tests Feature de RB-01..04 pasan; POST con `role=admin` → 422 |
| 3. Aprobación | Admin aprueba/rechaza docente | Docente `pending` → 403 en `POST /classrooms` |
| 4. Curriculum | CRUD admin + desbloqueo + progreso | RB-05: intento de abrir lección bloqueada → 403 |
| 5. Diagnostic | Pretest 12-15 preguntas, reanudable, sin XP | Interrupción a mitad de intento → reanudar sin duplicar (idempotencia); XP delta = 0 |
| 6. Evaluación | Quiz Card-Flow + grading server | El score lo calcula EL SERVIDOR (test: cliente envía opción, no score); intentos ordinales correctos |
| 7. Gamificación | XP, racha, badges, ranking global+aula | Reintento 2 → XP×0.8, reintento 3 → XP×0.64 (RB-06/15); badge no duplica (RB-07) |
| 8. Classrooms | Código inscr + analítica por aula | Código regenerado invalida el anterior; estudiantes `removed` no cuentan |
| 9. Reporting | PDFs docente/admin | Archivo descargado inicia con `%PDF-`; 0 endpoints Excel |
| 10. OfflineSync | Manifest + cola sync | Cuestionario offline rebotado → 409 y sin XP; lecciones leídas sí se actualizan |
| 11. Audit | Bitácora + tickets | Cada acción admin inserta fila en `audit_logs` |
| 12. Piloto | Seeds con 108 estudiantes + pre/post | Export consolidado pre/post con n≥108 filas por usuario |

---

## 9. Riesgos y puntos que cruzan al frontend

1. **Contrato de "nivel"**: el FE debe mostrar dos métricas distintas (`learning_track` vs `xp_level`); no fusionarlas en una sola.
2. **Fugas de respuestas correctas**: en quizzes, la API nunca envía `is_correct` en el payload inicial de preguntas. Solo tras responder se devuelve `{is_correct, feedback}` de la opción elegida, y la explicación completa de la pregunta llega en el submit (RB-10).
3. **Card-Flow**: cada tarjeta se confirma en servidor; el FE no debe validar aciertos localmente ni asumir orden fijo de opciones.
4. **Rankings**: paginados por cursor desde el día 1 (n piloto 108, diseñado para crecer).
5. **Offline**: el FE recibe del backend un manifest con hashes de versión para invalidar su caché local; no debe calcular recompensas.
6. **Rate limit**: 5 intentos/min por email+IP en `/login`; el FE debe mostrar el mensaje 429 en español.

## 10. Decisiones ABIERTAS (no bloqueantes; se aplica el valor por defecto si no indicas lo contrario)

1. **Reglas de insignias**: gestionadas en BD con seed inicial (por defecto); si prefieres que solo el seed las defina, se retira el CRUD admin.
2. **Hosting/HTTPS** de producción — indefinido; el docker-compose local no lo cubre.
3. **Umbral de riesgo académico** (tasa_aprobación < 70 % e intentos > 3) — propuesta provisional; se confirma en Fase 10 con datos reales.
