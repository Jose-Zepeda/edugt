# EduGT — Contexto completo del proyecto

> **Última actualización:** 2026-10-02
> **Propósito:** este documento contextualiza TODO el proyecto de tesis EduGT para cualquier persona/IA nueva que lo retome: qué es, cómo está construido, qué tanto avance lleva, qué modos de trabajo conviene seguir y qué queda por resolver.
> **Fuente de requisitos original:** documento de tesis (UMG Jutiapa — Proyecto de Graduación II 2026); resumen operativo en `docs/plans/2026-09-29-backend-arquitectura.md`.

---

## 1. Qué es EduGT

Plataforma web educativa gamificada, **gratuita, en español, mobile-first**, para enseñar **lógica de programación** a estudiantes de cuarto/quinto bachillerato y perito contador de Jutiapa, Guatemala.

**Temario:** algoritmos y pseudocódigo · variables y tipos de datos · condicionales · ciclos · diagramas de flujo · pensamiento computacional.

**Roles:** Estudiante · Docente · Administrador (el admin nunca se registra por la web — solo CLI/otro admin).

**Objetivo de investigación:** pilotar con n≥108 estudiantes, pretest/postest comparables y análisis Wilcoxon para medir el cambio de aprendizaje. **El pretest y postest quedan fuera de la gamificación** (sin XP, sin insignias, sin ranking — RB-11).

---

## 2. Stack (obligatorio de la tesis)

| Capa | Tecnología |
|---|---|
| Backend | **Laravel 12** (PHP 8.2+), Eloquent ORM, Sanctum (tokens), Socialite (Google OAuth), Dompdf (reportes) |
| Base de datos | **MySQL 8.0** (en Docker) |
| Infraestructura | **Docker + Docker Compose** (nginx + php-fpm + mysql + redis) — reemplaza XAMPP |
| Frontend | **HTML5 + CSS3 + JS vanilla + Bootstrap 5.3** (sin Tailwind — prohibido por la tesis) |
| Testing backend | **Pest 3.8** (51 tests, 174 aserciones) |
| Frontend dev server | `python -m http.server 5500` (estático, simple) |

**Estado de la BD del piloto:** seeds de QA (13 usuarios); el dataset real n≥108 se siembra cuando exista la lista de inscritos.

---

## 3. Arquitectura backend (resumen)

Monolito modular con SRP/DIP. 12 módulos de dominio con dependencias unidireccionales:

```
Auth | Users | Curriculum | Diagnostic | Assessments | Gamification
Classrooms | Analytics | Reporting | Audit | Support | OfflineSync
```

- **Contratos DIP** (interfaces inyectadas desde `AppServiceProvider`):
  `GoogleOAuthGateway`, `AuditLogger`, `XpLedger`, `StreakService`, `BadgeAwarder`, `RankingService`, `UnlockEvaluator`, `AnalyticsQuery`, `ReportExporter`.
- **Server-authoritative (RB-10):** el navegador jamás calcula score/XP/desbloqueos. Todo se califica en servidor en el submit.
- **Ledger append-only de XP** (`xp_ledger_entries`): la fuente de verdad del puntaje; el perfil materializa el total.
- **Mapeo detalle→archivo:** `docs/plans/2026-09-29-backend-arquitectura.md` (§2..§8).

### 3.1 Los dos "niveles" (decisión crítica de diseño)

La tesis usa la palabra "nivel" con **dos** significados. El diseño los separa siempre:

| Concepto | Campo | Asignado por |
|---|---|---|
| Ruta de aprendizaje (pretest) | `users.learning_track` (beginner/intermediate/advanced) | `DiagnosticLevelAssigner` |
| Nivel de gamificación | `user_xp_profile.xp_level` (int) | `XpLedger` |

### 3.2 Reglas de negocio implementadas (RB-1..RB-17)

Todas con test que las verifica — detalle en la tabla §6 del plan de arquitectura. Las tres más estructurales:

- **RB-01:** el rol `admin` jamás se acepta desde el cliente (ni registro ni Google).
- **RB-05:** desbloqueo secuencial de lecciones + quiz aprobado ≥ 70/100 + nivel XP requerido por el módulo.
- **RB-06/15:** reintentos ilimitados con `XP_n = floor(base × 0.8^(n-1))` (intento 1 = base; 2 = 40 % menos; 3 = 36 %...).
- **RB-07:** insignias únicas por usuario (`UNIQUE(user_id, badge_id)`).
- **RB-08:** ranking solo de quizzes server-side validados.
- **RB-11:** pretest/postest fuera de gamificación (verificado: no crean perfil XP).
- **RB-12:** reportes solo PDF (sin endpoint Excel; magic bytes `%PDF-` verificados en test).
- **RB-13/14:** offline solo lectura; un quiz offline no existe (se rechaza al sincronizar); el perfil XP no lo ve.

---

## 4. Progreso realizado

### 4.1 Backend — 18 commits, 51 tests, 100% del alcance funcional

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Docker (nginx+php-fpm+mysql8+redis) + scaffold Laravel 12 + `/api/v1/health` | ✅ |
| 1 | 9 migraciones (users, gamification 5 tablas, curriculum 4, assessments 5, diagnostic 4, classrooms 3, audit/support 2, Sanctum) | ✅ |
| 2 | Registro tradicional + login + logout + `/me` con 423/403/429; mensajes `lang/es` | ✅ |
| 3 | Aprobación docentes + comando `edugt:admin-create` + AuditLogger DIP + credenciales Google en `.env` (no en repo) | ✅ |
| 4 | Models + `UnlockEvaluator` (RB-05), `CompleteLesson` (XP una sola vez), racha TZ `America/Guatemala` (RB-16) | ✅ |
| 5 | Pretest reanudable (guarda avance por Pregunta con POST por respuesta), 409 si ya completó, `DiagnosticLevelAssigner` | ✅ |
| 6 | Quiz Card-Flow reintentos, XP = `floor(base × 0.8^(n-1))` (secuencia 50→40→32 verificada en test) | ✅ |
| 7 | Badges vía reglas declarativas en BD + `RankingServiceDb` (orden XP desc) | ✅ |
| 8 | Classrooms con código regenerable, join idempotente, RB-09 policies | ✅ |
| 9 | Analytics SQL agregado + reportes PDF (Dompdf) + pre/post RF-28 | ✅ |
| 10 | Offline bundle + sync con merge idempotente | ✅ |
| 11 | Support tickets + audit-logs (endpoints admin) | ✅ |
| 12 | Seeds: 6 insignias de la tesis con reglas, 2 módulos × 4 lecciones temáticas, quizzes de 5 preguntas con rotación A/B/C determinista, 28 preguntas diagnósticas (2 por dimensión con problemas distintos), 13 usuarios QA, 2 aulas con códigos | ✅ |
| Extra 13 | Google OAuth completo (redirect/callback/complete) + google-landing con formulario rol | ✅ |

### 4.2 Frontend — 9 commits, 8 vistas, todas conectadas a la API real

| Página | Qué hace |
|---|---|
| `index.html` | Login tradicional + "Continuar con Google" + registro con rol student/teacher (RB-01) |
| `estudiante.html` | Dashboard: saludo contextual, hero XP con barra, tarjeta racha, CTA pretest si no hay `learning_track`, ruta con estados (abrir/continuar/bloqueada), insignias (obtenidas con icono vs bloqueadas en gris), ranking pestañas global/aula, nav inferior móvil |
| `diagnostico.html` | Pretest/postest Card-Flow: 14 preguntas, 1 por POST (reanudable), dimensión etiquetada en español, opciones SIN `is_correct` |
| `leccion.html` | Markdown sanitizado (DOMPurify) + pseudocódigo en bloque oscuro + cronómetro de tiempo real → POST de tiempo al completar |
| `quiz.html` | Card-Flow con **feedback inmediato por respuesta** (verde/rojo con explicación); submit server-side; pantalla resultado con score/XP/intento |
| `docente.html` | Mis aulas con código de inscripción (copiar/regenerar), contador de estudiantes, modal de creación, generación reporte PDF |
| `admin.html` | Docentes pendientes (aprobar/rechazar), tickets con filtros de estado, bitácora de acciones administrativas con fecha |
| `google-landing.html` | Formulario posterior de Google: foto/nombre de Google + elección de rol; para cuentas existentes → sesión directa |
| `offline.html` + `leer-offline.html` | Descarga bundle (8 lecciones → IndexedDB), lectura sin conexión, cola de sync con mensajería clara (RB-13/14) |

### 4.3 Identidad visual

- Paleta lavanda/violeta (referencias del usuario) con WCAG AA en todos los pares medidos.
- **Logo propio diseñado por el usuario** (squircle violeta, "E" de circuito blanco, estrella): 7 tamaños (`logo-mark-16..512`, todos RGBA con transparencia) + `favicon.ico` (16/32/48). Integrado en favicon, sidebar, avatar y login.
- Ilustraciones: iconos lucide/Bootstrap Icons; sin banco de imágenes.

### 4.4 Cómo levantar todo (si el equipo se apagó)

```bash
# 1. Docker Desktop → luego:
cd C:\Users\Jose\Desktop\EduGT2\backend
docker compose start        # o: docker start edugt-app edugt-nginx edugt-mysql edugt-redis

# 2. Frontend estático:
cd C:\Users\Jose\Desktop\EduGT2\frontend
python -m http.server 5500

# 3. Navegador: http://localhost:5500
```

**Cuentas de prueba:** `estudiante1@edugt.test / estudiante1234` · `marta@edugt.test / docente1234` · `admin@edugt.test / admin1234` · `pendiente@edugt.test / docente1234`.

---

## 5. Modos de trabajo (cómo continuar este proyecto)

1. **Un cambio = un commit.** Backend en `EduGT2/backend/.git`, frontend en `EduGT2/frontend/.git` (dos repos).
2. **El contrato primero:** cualquier endpoint novo o campo extra se documenta en el plan de arquitectura §7 antes de implementarse, y el FE lo consume exactamente como está escrita esa firma.
3. **Renders para UI:** toda pantalla nueva se verifica renderizándola (headless Edge/Chrome + lectura de imagen) **antes** de darla por buena. El estándar: sin texto cortado, sin solapes, contraste ≥ 4.5:1 medido por script, iconos presentes.
4. **Server-authoritative siempre:** un_FEATURE nuevo del FE nunca calcula recompensas.
5. **Tests dependen de seeds deterministas** — sus fixtures viven en `database/seeders/`; nunca la UI genera fixtures propios de negocio.
6. **El rol admin solo viaja por CLI** (`edugt:admin-create`); jamás en UI pública.
7. **Re-seed tras cambios de seeds:** `docker exec edugt-app php artisan migrate:fresh --seed` (destruye datos de prueba).

---

## 6. Problemas encontrados y resueltos (trazabilidad)

| Problema | Causa | Solución final |
|---|---|---|
| `throttle:register,1` lanzaba `MissingRateLimiterException` | El named-limiter necesita ≤3 args | `throttle:register` a secas; el número vive en `RateLimiter::for` |
| Pest pedía PHP 8.4 | Pest 4.x + `symfony/clock` 8.x | Downgrade de pest a 3.x + `symfony/clock` 7.x fijado |
| Tests se autenticaban con el usuario equivocado | Sanctum guard cacheao usuario entre requests del mismo mensaje | helper `clean_guard()` antes de cada request en tests (limpia guard) |
| Tareas migran fallando por índice largo | Nombre de constraint > 64 chars MySQL | Nombre explícito corto en índice único |
| Chars corruptos en `/api/health` seguido | Ruta duplicada | Depurada una sola en `routes/api.php` (consistente con §7) |
| Botón Google daba 500 | `Socialite` necesita sesión (state) y la ruta era API | Redirect/callback con middleware `web` |
| Callback de Google pintaba JSON crudo | API de formularios no renderiza para browser | Callback ahora hace redirect() al FE (`google-landing.html` con token/rol) |
| El redirect del callback iba a :3000 | Cache de configs antiguo/runtime | `feUrl()` ahora lee directo `env('FRONTEND_URL', 5500)` sin config-cache |
| `storage/logs` sin permisos | El volumen Docker sobre escribió dueño de git | chown/chmod dentro del contenedor |
| Las 4 lecciones se llamaban iguales; la correcta siempre era A | Seeds monótonos | Títulos temáticos por lección + rotación determinista A/B/C (distr. verificada en tinker) |
| Placeholder "[pre] / decomposition" visible | Seeds sin contenido real | 14 enunciados con mini-ejercicios de pseudocódigo real por dimensión |
| "canvas problemático con imagen"-varios | formatos RGBA vs RGB (transparencia) | Confirmar alfa por archivo; original RGBA transparente integrado |

---

## 7. Aspectos a resolver (pendientes)

### 7.1 Acción del usuario (bloqueos operativos)

1. **Google Cloud Console** — en el OAuth client del proyecto:
   - `Authorized redirect URIs` → `http://localhost:8080/api/v1/auth/google/callback` ✅ (ya agregado)
   - `Authorized origins` → agregar `http://localhost:5500` (requerido para que el navegador deje iniciar el flujo desde el login)
2. **Hosting/HTTPS de producción** — indefinido; el docker-compose actual es local. Para piloto real se necesita un servidor (VPS) con dominio.
3. **Dataset del piloto** — lista real de n≥108 estudiantes (csv de inscritos) para sembrar el dataset completo antes del estudio.

### 7.2 Desarrollo restante (opcional/mejoras)

1. **Gestión de contenido** de misiones/módulos/lecciones por web (admin CRUD) — hoy solo seeds/CLI.
2. **Habilitación de postest por aula** (UI docente/admin).
3. **Historial de intentos** del quiz (RF-14 — endpoint existe, falta vista FE).
4. **Analítica docente avanzada en UI** (graficas de uso vs reporte actual).
5. **Cypress E2E** del FE (la tesis lo lista; los Pest del BE ya cubren la mitad equivalente).
6. **Pint + PHPStan** en backend (herramientas de calidad exigidas formalmente por la tesis).
7. **PWA/Service Worker** para caché de assets estáticos del FE en modo offline (intermedio).

### 7.3 Riesgos técnicos conocidos

| Riesgo | Mitigación actual | Falta |
|---|---|---|
| El runtime Docker se desestabilizó varias veces (daemon cayó al abrir sesión) | scripts de arranque rápido (docker compose start) | monitoreo del stack / auto-restart |
| Tokens de Google pueden quedar vinculados a la IP/contenedor | el flujo usa solo `google_id` + token Sanctum propio | revisión en producción HTTPS |
| El http.server de Python es efímero | se relanza rápido con 1 comando | servicio permanente (nginx ya lo serviría en prod) |
| Distintas IAs trabajanto simultáneo → conflicto de contrato | `AGENTS.md` con matriz de propiedad | PRs/reviews humanas |

---

## 8. Estructura del repo

```
EduGT2/
├── AGENTS.md                      ← contrato de coordinación backend↔frontend
├── CONTEXT.md                     ← ESTE documento
├── docs/
│   └── plans/2026-09-29-backend-arquitectura.md   ← plan maestro (arq., RB-IDs, API, fases)
├── backend/                       ← app Laravel 12 (repo git propio)
│   ├── app/Domain/{Auth,Users,Curriculum,Diagnostic,Assessments,Gamification,Classrooms,Analytics,Reporting,Audit,Support,OfflineSync}
│   ├── app/Http/Controllers/Api/V1/   ← 10 controladores delgados
│   ├── app/Http/Controllers/…         ← (ver docs/plans §5)
│   ├── database/migrations/           ← 9 migraciones + Sanctum
│   ├── database/seeders/              ← ContentSeeder + DevUsersSeeder
│   ├── docker-compose.yml             ← nginx + app + mysql8 + redis
│   ├── docker/{php,nginx}/            ← Dockerfile y conf de nginx
│   ├── tests/Feature/                 ← 6 archivos Pest (51 tests)
│   └── lang/es/auth.php               ← mensajes en español
└── frontend/                      ← SPA estática (repo git propio)
    ├── index.html, estudiante.html, diagnostico.html, leccion.html, quiz.html,
    │   docente.html, admin.html, google-landing.html, offline.html, leer-offline.html
    ├── assets/img/logo-mark-*.png     ← logo del usuario (RGBA) 7 tamaños
    ├── assets/css/app.css             ← tema custom (paleta lavanda, WCAG AA)
    ├── assets/js/{api,auth,panel,diagnostico,leccion,quiz,docente,admin,offline}.js
    └── favicon.ico                    ← 16/32/48 de un solo archivo
```

---

## 9. Diccionario rápido (para retomar sin volver a leer todo)

- **Contract §7** = firma de la API en el plan de arquitectura (rutas, campos, reglas).
- **RB-xx** = regla de negocio con test que la protege.
- **RF-xx** = requisito funcional del documento de tesis (1..28).
- **learning_track** = ruta de aprendizaje (pretest) — **NO** confundir con `xp_level`.
- **bundle/manifest** = paquete offline: lecciones con hashes de versión.
- **`edugt-app` / `edugt-nginx` / `edugt-mysql` / `edugt-redis`** = contenedores del compose.
- **Secretos** (Google keys): solo en `.env` local (fuera del repo git).
