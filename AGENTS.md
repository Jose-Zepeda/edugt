# AGENTS.md — Reglas de coordinación EduGT (Backend ↔ Frontend)

> Fuente única de verdad de la arquitectura: `docs/plans/2026-09-29-backend-arquitectura.md`.
> Este archivo resume SOLO lo que ambas IAs deben compartir. Si el FE necesita un detalle del dominio, lo toma de ese documento, nunca lo inventa.

## 1. Propiedad de archivos

| Agente | Escribe | No toca |
|---|---|---|
| **Backend (esta IA)** | `backend/` completo (código, migraciones, seeds, tests, docker-compose), `docs/plans/` | `frontend/`, pantallas, CSS |
| **Frontend (otra IA)** | `frontend/` (HTML5/CSS3/JS + Bootstrap 5.3), pruebas Cypress | `backend/`, migraciones, DDL, seeds de negocio, endpoint definitions |

El contrato de API de `docs/plans/` es congelado por backend: si el FE necesita un campo nuevo, lo solicita; no lo agrega unilateralmente.

## 2. Contratos compartidos (tomados de la tesis)

- **IDs de requisitos:** RF-1..RF-28 (mapeo módulo/fase en §2.1 del doc de arquitectura).
- **Reglas de negocio:** RB-1..RB-17 (tabla completa en §6 del doc). Las dos que el FE más confunde:
  - **RB-10:** el navegador NUNCA es fuente de verdad: el FE jamás calcula score, XP, desbloqueos ni insignias.
  - **RB-11:** pretest/postest fuera de gamificación — sin XP, sin insignias, sin ranking.
  - **RB-13:** un cuestionario offline no existe: al sincronizar se rechaza (409) y no otorga XP.
- **Dos significados de "nivel"** (no fusionar en UI):
  1. `learning_track` — ruta de aprendizaje asignada por el pretest (`beginner|intermediate|advanced`).
  2. `xp_level` — nivel numérico de gamificación.
- **Formato de error común (todas las rutas):**

```json
{ "message": "texto en español legible", "errors": { "campo": ["msg corto"] }, "code": "SNAKE_CASE_CODE", "status": 422 }
```

- **Códigos que FE debe manejar explícitamente:** `TEACHER_PENDING_APPROVAL` (423), `DIAGNOSTIC_ALREADY_COMPLETED` (409), `VALIDATION_ERROR` (422), `LOCKED_CONTENT` (403), `RATE_LIMITED` (429).
- **Datos de prueba:** para desarrollo QA el backend entrega seeds con 3 docentes activos, 1 pendiente, 8 estudiantes, 2 aulas con códigos, 1 misión × 2 módulos × 4 lecciones, 1 quiz por módulo. El FE no debe crear fixtures propios en BD de negocio.

## 3. Flujo de coordinación

1. Backend entrega una Fase → actualiza el estado en la sección "Fases" del doc de arquitectura.
2. FE consume la API contra `http://localhost:8080` (docker) con Bearer Sanctum.
3. Cambio de contrato: se propone en este archivo + doc de arquitectura, se acuerda por mensaje, y solo entonces se implementa.
4. Ambos agentes usan Git en el mismo repo: ramas `feat/backend-*` y `feat/frontend-*`; `main` queda estable.
