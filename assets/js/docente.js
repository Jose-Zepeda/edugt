/* ==========================================================================
   EduGT — Panel docente (RF-19, RF-20, RF-21, RF-22)
   ========================================================================== */
'use strict';

Auth.load();

// Guard de rol: solo docentes (y admin de contingencia)
if (!Auth.token || !Auth.user) {
  window.location.href = 'index.html';
} else if (Auth.user.role === 'student') {
  // El estudiante no entra al panel docente
  window.location.href = 'estudiante.html';
}

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

let currentClassrooms = [];
let classroomToDeleteId = null;

function showMessage(message, kind = 'danger') {
  const el = $('#alert');
  if (!el) return;
  el.className = `alert alert-${kind}`;
  el.textContent = message;
  el.classList.remove('d-none');
}

/* ---------------- Saludo ---------------- */
function renderGreeting() {
  const kicker = $('#greeting-kicker');
  const greeting = $('#greeting');
  if (kicker) kicker.textContent = 'Buen día';
  if (greeting) greeting.textContent = `Docente ${Auth.user.name}`;
}

/* ---------------- Pestañas dinámicas (Opción A) ---------------- */
function switchTab(tabId) {
  const validTabs = ['aulas', 'reportes'];
  const target = validTabs.includes(tabId) ? tabId : 'aulas';

  // Mostrar la sección correspondiente
  validTabs.forEach((t) => {
    const section = document.getElementById(`tab-${t}`);
    if (section) {
      if (t === target) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    }
  });

  // Actualizar estado en sidebar
  document.querySelectorAll('#docente-nav [data-tab-target]').forEach((el) => {
    if (el.getAttribute('data-tab-target') === target) {
      el.classList.add('active');
    } else {
      el.classList.remove('active');
    }
  });

  // Actualizar estado en nav inferior móvil
  document.querySelectorAll('#mobile-docente-nav [data-tab-target]').forEach((el) => {
    if (el.getAttribute('data-tab-target') === target) {
      el.classList.add('active');
    } else {
      el.classList.remove('active');
    }
  });

  try {
    history.replaceState(null, '', `#${target}`);
  } catch {
    // Ignorar si el navegador restringe el historial
  }

  if (target === 'reportes' && Auth.user?.status !== 'pending_approval') {
    loadLiveAnalytics($('#reporte-aula')?.value || '');
  }
}

// Exponer globalmente para onclick inline
window.switchDocenteTab = switchTab;

// Sincronizar pestaña inicial según hash de URL
window.addEventListener('hashchange', () => {
  const hash = window.location.hash.replace('#', '');
  if (hash) switchTab(hash);
});

/* ---------------- Aulas (RF-19) ---------------- */
async function loadClassrooms() {
  const { data } = await api('/classrooms');
  currentClassrooms = data.data || [];

  const box = $('#classrooms');
  if (!box) return;

  if (currentClassrooms.length === 0) {
    box.innerHTML = `
      <div class="col-12">
        <div class="card-edugt p-4 p-md-5 text-center">
          <div class="stat-icon mx-auto mb-3 shadow-sm" style="width:64px;height:64px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:2rem;border-radius:20px;">
            <i class="bi bi-people-fill"></i>
          </div>
          <h3 class="h5 fw-bold mb-1" style="color:var(--edugt-text)">Aún no tienes aulas registradas</h3>
          <p class="text-muted mb-4 mx-auto" style="max-width:480px;font-size:.9rem;">
            Crea tu primera aula para generar un código único de inscripción que podrás compartir con tus estudiantes.
          </p>

          <div class="row g-3 mx-auto mb-4 text-start" style="max-width:740px;">
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-4 h-100 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-circle" style="width:24px;height:24px;display:grid;place-items:center;font-size:.8rem;">1</span>
                  <strong class="small" style="color:var(--edugt-text);">Crea tu aula</strong>
                </div>
                <p class="small text-muted mb-0">Define el nombre del grupo, grado escolar y ciclo académico.</p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-4 h-100 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-circle" style="width:24px;height:24px;display:grid;place-items:center;font-size:.8rem;">2</span>
                  <strong class="small" style="color:var(--edugt-text);">Comparte el código</strong>
                </div>
                <p class="small text-muted mb-0">Entrega el código de 8 caracteres a tus alumnos para que se unan.</p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="p-3 rounded-4 h-100 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-circle" style="width:24px;height:24px;display:grid;place-items:center;font-size:.8rem;">3</span>
                  <strong class="small" style="color:var(--edugt-text);">Monitorea y evalúa</strong>
                </div>
                <p class="small text-muted mb-0">Revisa el avance individual y descarga informes PDF oficiales.</p>
              </div>
            </div>
          </div>

          <button class="btn btn-edugt px-4 py-2.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-aula">
            <i class="bi bi-plus-lg me-1"></i> Crear mi primera aula
          </button>
        </div>
      </div>`;
    fillSelect([]);
    return;
  }

  box.innerHTML = currentClassrooms.map((c) => {
    const studentCount = c.students_count ?? 0;
    const hasStudents = studentCount > 0;

    return `
    <div class="col-md-6 col-xl-4">
      <article class="card-edugt p-4 h-100 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
              <div class="stat-icon" style="background:var(--edugt-primary-soft);color:var(--edugt-primary);width:38px;height:38px;font-size:1.1rem;">
                <i class="bi bi-people-fill"></i>
              </div>
              <span class="pill-edugt ${c.is_active !== false ? 'pill-done' : 'pill-locked'}">${c.is_active !== false ? 'Activa' : 'Archivada'}</span>
            </div>
            <div class="d-flex align-items-center gap-1">
              <button class="btn btn-sm btn-google p-1 px-2" data-edit-classroom="${c.id}" title="Editar aula" aria-label="Editar aula">
                <i class="bi bi-pencil" style="font-size:.9rem;"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger p-1 px-2" data-delete-classroom="${c.id}" title="Eliminar aula" aria-label="Eliminar aula" style="border-radius:12px;">
                <i class="bi bi-trash3" style="font-size:.9rem;"></i>
              </button>
            </div>
          </div>
          
          <h3 class="h6 fw-bold mb-1">${esc(c.name)}</h3>
          <div class="d-flex align-items-center gap-2 text-muted small">
            <span>${esc(c.institution || 'Sin institución')}</span>
            <span>•</span>
            <span>${esc(c.academic_year)}</span>
          </div>

          <div class="code-access-box border rounded-4 p-3 mt-3 d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Código de acceso</small>
              <div class="fw-bold code-display-text" style="font-size:1.15rem;letter-spacing:.12em" data-code="${c.id}">${esc(c.code ?? '—')}</div>
            </div>
            <div class="d-flex gap-1">
              <button class="btn btn-sm btn-edugt" data-copy="${c.id}" title="Copiar código" aria-label="Copiar código"><i class="bi bi-clipboard"></i></button>
              <button class="btn btn-sm btn-google" data-regen="${c.id}" title="Regenerar código" aria-label="Regenerar código"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 mt-3">
            <i class="bi ${hasStudents ? 'bi-person-check-fill text-success' : 'bi-person text-muted'}"></i>
            <span class="fw-bold">${studentCount}</span>
            <span class="text-muted" style="font-size:.9rem;">${studentCount === 1 ? 'estudiante inscrito' : 'estudiantes inscritos'}</span>
          </div>
        </div>

        <div class="mt-3 pt-2">
          ${hasStudents ? `
            <button class="btn btn-sm btn-edugt-soft w-100 d-flex align-items-center justify-content-center gap-2" 
                    data-view-students="${c.id}" 
                    data-classroom-name="${esc(c.name)}" 
                    data-student-count="${studentCount}">
              <i class="bi bi-people-fill"></i> Ver estudiantes (${studentCount})
            </button>
          ` : `
            <div class="classroom-empty-students text-center py-2 border rounded-3" style="font-size:0.83rem;">
              <i class="bi bi-info-circle me-1"></i> Sin estudiantes inscritos aún
            </div>
          `}
        </div>
      </article>
    </div>`;
  }).join('');

  fillSelect(currentClassrooms.map((c) => ({ id: c.id, name: c.name })));

  // Copiar código
  box.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    const code = box.querySelector(`[data-code="${b.dataset.copy}"]`)?.textContent.trim() ?? '';
    navigator.clipboard?.writeText(code);
    b.innerHTML = '<i class="bi bi-check-lg"></i>';
    setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1200);
  }));

  // Regenerar código
  box.querySelectorAll('[data-regen]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    try {
      const { data: r } = await api(`/classrooms/${b.dataset.regen}/codes/regenerate`, { method: 'POST' });
      const cell = box.querySelector(`[data-code="${b.dataset.regen}"]`);
      if (cell) cell.textContent = r.code;
    } catch (err) {
      showMessage(err.message);
    } finally {
      b.disabled = false;
    }
  }));

  // Ver lista de estudiantes inscritos
  box.querySelectorAll('[data-view-students]').forEach((b) => b.addEventListener('click', () => {
    openStudentsModal(b.dataset.viewStudents, b.dataset.classroomName, b.dataset.studentCount);
  }));

  // Editar aula
  box.querySelectorAll('[data-edit-classroom]').forEach((b) => b.addEventListener('click', () => {
    openEditModal(b.dataset.editClassroom);
  }));

  // Eliminar aula
  box.querySelectorAll('[data-delete-classroom]').forEach((b) => b.addEventListener('click', () => {
    openDeleteModal(b.dataset.deleteClassroom);
  }));
}

function fillSelect(classrooms) {
  const sel = $('#reporte-aula');
  if (!sel) return;
  const prevVal = sel.value || '';
  sel.innerHTML = '<option value="">Todas mis aulas (Consolidado)</option>' +
    classrooms.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  if (prevVal && classrooms.some((c) => String(c.id) === String(prevVal))) {
    sel.value = prevVal;
  }
}

$('#reporte-aula')?.addEventListener('change', (e) => {
  loadLiveAnalytics(e.target.value || '');
});

async function loadLiveAnalytics(classroomId = '') {
  const box = $('#live-analytics-dashboard');
  if (!box) return;

  box.innerHTML = `
    <div class="card-edugt p-5 text-center mb-4">
      <div class="spinner-border text-primary mb-3" role="status"></div>
      <div class="fw-bold" style="color:var(--edugt-text);">Calculando analíticas e indicadores pedagógicos en tiempo real...</div>
      <small class="text-muted">Consultando telemetría de lecciones, evaluaciones y diagnósticos Pre-test / Post-test</small>
    </div>`;

  try {
    const query = classroomId ? `?classroom_id=${encodeURIComponent(classroomId)}` : '';
    const { data } = await api(`/analytics/overview${query}`);
    renderLiveAnalyticsDashboard(data.data, classroomId);
  } catch (err) {
    box.innerHTML = `
      <div class="alert alert-warning rounded-4 border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        No se pudieron cargar las analíticas en vivo: ${esc(err.message || 'Error de conexión')}
      </div>`;
  }
}

function renderLiveAnalyticsDashboard(payload, selectedClassroomId = '') {
  const box = $('#live-analytics-dashboard');
  if (!box || !payload) return;

  const meta = payload.meta || {};
  const metrics = payload.metrics || {};
  const prePost = payload.pre_post || {};
  const dimensions = Array.isArray(payload.dimensions) ? payload.dimensions : [];
  const modulesSummary = Array.isArray(payload.modules_summary) ? payload.modules_summary : [];
  const atRisk = Array.isArray(payload.at_risk) ? payload.at_risk : [];
  const topFailed = Array.isArray(payload.top_failed_questions) ? payload.top_failed_questions : [];

  const studentsCount = meta.students_count ?? 0;
  const avgProgress = meta.avg_course_progress ?? 0;
  const passRate = metrics.pass_rate ?? 0;
  const retentionRate = metrics.retention_rate ?? 0;

  const preAvg = prePost.overall_pre_avg ?? prePost.pre_avg ?? null;
  const postAvg = prePost.overall_post_avg ?? prePost.post_avg ?? null;
  const meanGain = prePost.mean_gain !== null && prePost.mean_gain !== undefined
    ? prePost.mean_gain
    : (preAvg !== null && postAvg !== null ? Number((postAvg - preAvg).toFixed(1)) : null);
  const hakeG = prePost.hake_g;
  const hakeLevel = prePost.hake_level || 'Datos insuficientes';

  let hakeBadgeClass = 'bg-secondary bg-opacity-10 text-muted border-secondary';
  if (hakeG !== null && hakeG !== undefined) {
    if (hakeG >= 0.7) hakeBadgeClass = 'bg-success bg-opacity-10 text-success border-success';
    else if (hakeG >= 0.3) hakeBadgeClass = 'bg-primary bg-opacity-10 text-primary border-primary';
    else hakeBadgeClass = 'bg-warning bg-opacity-10 text-warning border-warning';
  }

  const hakeBarPct = hakeG !== null && hakeG !== undefined
    ? Math.min(100, Math.max(0, Math.round(hakeG * 100)))
    : 0;

  const dimsHtml = dimensions.map((d) => {
    const pPre = d.pre_avg !== null && d.pre_avg !== undefined ? Math.round(d.pre_avg) : null;
    const pPost = d.post_avg !== null && d.post_avg !== undefined ? Math.round(d.post_avg) : null;
    const delta = (pPre !== null && pPost !== null) ? (pPost - pPre) : null;
    const deltaBadge = delta !== null
      ? `<span class="badge rounded-pill ${delta >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'} px-2 py-0.5" style="font-size:.7rem;">${delta >= 0 ? `+${delta}%` : `${delta}%`}</span>`
      : `<span class="badge rounded-pill bg-secondary bg-opacity-10 text-muted px-2 py-0.5" style="font-size:.7rem;">Sin Post-test</span>`;

    return `
      <div class="p-2.5 rounded-3 border mb-2" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="fw-bold small" style="color:var(--edugt-text);font-size:.8rem;">${esc(d.label)}</span>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted" style="font-size:.73rem;">
              Pre: <strong>${pPre !== null ? `${pPre}%` : '—'}</strong> · Post: <strong class="text-success">${pPost !== null ? `${pPost}%` : '—'}</strong>
            </small>
            ${deltaBadge}
          </div>
        </div>
        <div class="d-flex flex-column gap-1">
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="font-size:.66rem;width:28px;">Pre</span>
            <div class="flex-grow-1 rounded-pill overflow-hidden" style="height:6px;background:rgba(148,163,184,0.2);">
              <div style="width:${pPre ?? 0}%;height:100%;background:#6366f1;border-radius:999px;"></div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="font-size:.66rem;width:28px;">Post</span>
            <div class="flex-grow-1 rounded-pill overflow-hidden" style="height:6px;background:rgba(148,163,184,0.2);">
              <div style="width:${pPost ?? 0}%;height:100%;background:#10b981;border-radius:999px;"></div>
            </div>
          </div>
        </div>
      </div>`;
  }).join('');

  const modulesHtml = modulesSummary.map((m) => {
    const passPct = Math.min(100, Math.max(0, Math.round(m.pass_rate ?? 0)));
    return `
      <div class="col-12 col-md-4">
        <div class="p-3.5 rounded-4 border h-100 d-flex flex-column justify-content-between" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
          <div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge rounded-pill px-2.5 py-1 fw-bold" style="background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:.73rem;">
                Módulo ${m.number}
              </span>
              <span class="small text-muted fw-semibold" style="font-size:.75rem;">
                <i class="bi bi-journal-text me-1"></i>${m.lessons_count} lecciones
              </span>
            </div>
            <h5 class="h6 fw-bold mb-3" style="color:var(--edugt-text);">${esc(m.title)}</h5>
            <div class="row g-2 mb-3 text-center">
              <div class="col-4">
                <div class="p-2 rounded-3 border" style="background:var(--edugt-card);border-color:var(--edugt-border)!important;">
                  <div class="fw-bold" style="color:var(--edugt-text);font-size:.95rem;">${m.students_completed_lessons}/${studentsCount}</div>
                  <div class="text-muted" style="font-size:.68rem;">Lecciones 100%</div>
                </div>
              </div>
              <div class="col-4">
                <div class="p-2 rounded-3 border" style="background:var(--edugt-card);border-color:var(--edugt-border)!important;">
                  <div class="fw-bold text-primary" style="font-size:.95rem;">${m.avg_quiz_score !== null ? `${m.avg_quiz_score}%` : '—'}</div>
                  <div class="text-muted" style="font-size:.68rem;">Nota Media Quiz</div>
                </div>
              </div>
              <div class="col-4">
                <div class="p-2 rounded-3 border" style="background:var(--edugt-card);border-color:var(--edugt-border)!important;">
                  <div class="fw-bold text-success" style="font-size:.95rem;">${m.students_passed_quiz}/${studentsCount}</div>
                  <div class="text-muted" style="font-size:.68rem;">Quiz Aprobado</div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <div class="d-flex justify-content-between small mb-1" style="font-size:.75rem;">
              <span class="text-muted">Tasa de aprobación (${m.quiz_attempts} int.)</span>
              <strong style="color:var(--edugt-text);">${passPct}%</strong>
            </div>
            <div class="teacher-progress-track" style="height:7px;">
              <div class="teacher-progress-fill ${passPct >= 70 ? 'is-complete' : ''}" style="width:${passPct}%;"></div>
            </div>
          </div>
        </div>
      </div>`;
  }).join('');

  const atRiskHtml = atRisk.length > 0
    ? atRisk.map((r) => `
      <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 border mb-2" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
        <div class="min-w-0 pe-2">
          <div class="fw-bold text-truncate small" style="color:var(--edugt-text);">${esc(r.name)}</div>
          <div class="text-muted text-truncate" style="font-size:.74rem;">${esc(r.email)} · ${r.attempts} intentos fallidos</div>
        </div>
        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1 fw-bold flex-shrink-0">
          Prom: ${r.avg_score}%
        </span>
      </div>`).join('')
    : `
      <div class="p-4 text-center rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
        <i class="bi bi-shield-check text-success fs-3 d-block mb-1"></i>
        <div class="fw-bold small" style="color:var(--edugt-text);">Sin estudiantes en riesgo crítico</div>
        <small class="text-muted" style="font-size:.78rem;">Ningún estudiante acumula ≥2 intentos reprobados con promedio &lt;60 pts.</small>
      </div>`;

  const topFailedHtml = topFailed.length > 0
    ? topFailed.map((q) => `
      <div class="p-2.5 rounded-3 border mb-2" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <span class="badge rounded-pill px-2 py-0.5" style="background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:.68rem;">
            ${esc(q.module_title)}
          </span>
          <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-0.5 fw-bold" style="font-size:.72rem;">
            ${q.error_rate}% error (${q.total} resp.)
          </span>
        </div>
        <div class="small" style="color:var(--edugt-text);font-size:.8rem;line-height:1.35;">${esc(q.statement)}</div>
      </div>`).join('')
    : `
      <div class="p-4 text-center rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
        <i class="bi bi-check2-all text-primary fs-3 d-block mb-1"></i>
        <div class="fw-bold small" style="color:var(--edugt-text);">Sin reactivos críticos</div>
        <small class="text-muted" style="font-size:.78rem;">Aún no se registran respuestas suficientes para estimar tasas de error.</small>
      </div>`;

  box.innerHTML = `
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 fw-bold" style="font-size:.76rem;">
          <i class="bi bi-broadcast me-1"></i> Analítica en Vivo
        </span>
        <h3 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">${esc(meta.title || 'Consolidado de Aulas')}</h3>
        <small class="text-muted d-none d-sm-inline" style="font-size:.78rem;">· Actualizado: ${esc(meta.generated_at || 'Ahora')}</small>
      </div>
      <button type="button" class="btn btn-sm btn-google px-3 py-1.5" id="btn-refresh-analytics">
        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar métricas
      </button>
    </div>

    <!-- 4 KPIs Principales -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="card-edugt p-3.5 h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Población</span>
            <i class="bi bi-people-fill text-primary fs-5"></i>
          </div>
          <div class="h3 fw-bold mb-0" style="color:var(--edugt-text);">${studentsCount}</div>
          <small class="text-muted" style="font-size:.78rem;">Estudiantes activos en nómina</small>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="card-edugt p-3.5 h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Avance Curricular</span>
            <i class="bi bi-Map-fill bi-compass-fill text-info fs-5"></i>
          </div>
          <div class="h3 fw-bold mb-1" style="color:var(--edugt-primary);">${avgProgress}%</div>
          <div class="teacher-progress-track" style="height:6px;">
            <div class="teacher-progress-fill ${avgProgress >= 100 ? 'is-complete' : ''}" style="width:${Math.min(100, avgProgress)}%;"></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="card-edugt p-3.5 h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Aprobación Quizzes</span>
            <i class="bi bi-patch-check-fill text-success fs-5"></i>
          </div>
          <div class="h3 fw-bold mb-0 text-success">${passRate}%</div>
          <small class="text-muted" style="font-size:.78rem;">${metrics.passed_attempts ?? 0} de ${metrics.total_attempts ?? 0} intentos (Prom: ${metrics.avg_score ?? 0}%)</small>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="card-edugt p-3.5 h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Retención (14d)</span>
            <i class="bi bi-activity text-warning fs-5"></i>
          </div>
          <div class="h3 fw-bold mb-0" style="color:var(--edugt-text);">${retentionRate}%</div>
          <small class="text-muted" style="font-size:.78rem;">${metrics.active_students_14d ?? 0} estudiantes con actividad reciente</small>
        </div>
      </div>
    </div>

    <!-- Eficacia Pedagógica (Pre-test vs Post-test + Hake) y 7 Dimensiones -->
    <div class="card-edugt p-4 mb-4">
      <div class="row g-4 align-items-start">
        <div class="col-12 col-lg-5">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-graph-up-arrow text-primary fs-5"></i>
            <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">Impacto Pedagógico y Ganancia de Hake</h4>
          </div>
          <p class="small text-muted mb-3">
            Evaluación experimental del aprendizaje comparando el Diagnóstico Inicial (Pre-test) frente a la Evaluación Final (Post-test).
          </p>

          <div class="row g-2 mb-3 text-center">
            <div class="col-4">
              <div class="p-3 rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="small text-muted fw-bold text-uppercase" style="font-size:.68rem;">Pre-test (${prePost.pre_students ?? 0})</div>
                <div class="h4 fw-bold mb-0 mt-1" style="color:#6366f1;">${preAvg !== null ? `${preAvg}%` : '—'}</div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-3 rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="small text-muted fw-bold text-uppercase" style="font-size:.68rem;">Post-test (${prePost.post_students ?? 0})</div>
                <div class="h4 fw-bold mb-0 mt-1 text-success">${postAvg !== null ? `${postAvg}%` : '—'}</div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-3 rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
                <div class="small text-muted fw-bold text-uppercase" style="font-size:.68rem;">Ganancia Δ</div>
                <div class="h4 fw-bold mb-0 mt-1 ${meanGain !== null && meanGain >= 0 ? 'text-success' : 'text-muted'}">
                  ${meanGain !== null ? (meanGain >= 0 ? `+${meanGain}` : `${meanGain}`) : '—'}
                </div>
              </div>
            </div>
          </div>

          <div class="p-3.5 rounded-4 border" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <div>
                <div class="small fw-bold" style="color:var(--edugt-text);">Índice de Ganancia Normalizada (Hake <em>g</em>)</div>
                <small class="text-muted" style="font-size:.74rem;">Muestra emparejada: ${prePost.paired_students ?? 0} estudiantes con Pre y Post</small>
              </div>
              <span class="badge rounded-pill border px-2.5 py-1 fw-bold ${hakeBadgeClass}" style="font-size:.78rem;">
                g = ${hakeG !== null && hakeG !== undefined ? hakeG : 'N/A'}
              </span>
            </div>
            <div class="teacher-progress-track mb-2" style="height:8px;">
              <div class="teacher-progress-fill ${hakeBarPct >= 70 ? 'is-complete' : ''}" style="width:${hakeBarPct}%;"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="small fw-semibold" style="color:var(--edugt-text);font-size:.78rem;">${esc(hakeLevel)}</span>
              <span class="text-muted" style="font-size:.7rem;">Baja &lt;0.30 · Media 0.30–0.69 · Alta ≥0.70</span>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-7">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">
              <i class="bi bi-diagram-3-fill text-primary me-1"></i> Desempeño por Dimensión del Pensamiento Computacional
            </h4>
            <span class="small text-muted" style="font-size:.75rem;">7 ejes evaluados</span>
          </div>
          <div>
            ${dimsHtml}
          </div>
        </div>
      </div>
    </div>

    <!-- Rendimiento por Módulo Curricular -->
    <div class="card-edugt p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
          <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">
            <i class="bi bi-layers-fill text-primary me-1"></i> Avance y Aprobación por Módulo Curricular
          </h4>
          <small class="text-muted">Desglose de lecciones completadas y evaluaciones sumativas por cada módulo</small>
        </div>
      </div>
      <div class="row g-3">
        ${modulesHtml}
      </div>
    </div>

    <!-- Alertas Tempranas y Reactivos con Mayor Error -->
    <div class="row g-4 mb-2">
      <div class="col-12 col-lg-6">
        <div class="card-edugt p-4 h-100">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">
              <i class="bi bi-exclamation-octagon-fill text-danger me-1"></i> Detección Temprana: Estudiantes en Riesgo
            </h4>
            <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger px-2.5 py-1 fw-bold" style="font-size:.74rem;">
              ${atRisk.length} detectados
            </span>
          </div>
          ${atRiskHtml}
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card-edugt p-4 h-100">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">
              <i class="bi bi-patch-question-fill text-warning me-1"></i> Top 5 Reactivos con Mayor Tasa de Error
            </h4>
            <span class="small text-muted" style="font-size:.75rem;">Foco de refuerzo en clase</span>
          </div>
          ${topFailedHtml}
        </div>
      </div>
    </div>`;

  $('#btn-refresh-analytics')?.addEventListener('click', () => {
    loadLiveAnalytics(selectedClassroomId);
  });
}

/* ---------------- Modal Editar Aula ---------------- */
const editModalEl = $('#modal-editar-aula');
let editModalInstance = null;

function getEditModal() {
  if (!editModalInstance && editModalEl && window.bootstrap) {
    editModalInstance = new bootstrap.Modal(editModalEl);
  }
  return editModalInstance;
}

function openEditModal(classroomId) {
  const classroom = currentClassrooms.find((c) => String(c.id) === String(classroomId));
  if (!classroom) return;

  $('#edit-aula-id').value = classroom.id;
  $('#edit-aula-nombre').value = classroom.name || '';
  $('#edit-aula-institucion').value = classroom.institution || '';
  $('#edit-aula-grado').value = classroom.grade_level || '';
  $('#edit-aula-anio').value = classroom.academic_year || '2026';

  const alertEl = $('#modal-edit-alert');
  if (alertEl) alertEl.classList.add('d-none');

  getEditModal()?.show();
}

$('#form-editar-aula')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const alertEl = $('#modal-edit-alert');
  const btn = $('#btn-save-edit-aula');
  const id = $('#edit-aula-id').value;

  btn.disabled = true;
  btn.textContent = 'Guardando...';
  if (alertEl) alertEl.classList.add('d-none');

  try {
    await api(`/classrooms/${id}`, {
      method: 'PUT',
      body: {
        name: $('#edit-aula-nombre').value.trim(),
        institution: $('#edit-aula-institucion').value.trim() || null,
        grade_level: $('#edit-aula-grado').value.trim() || null,
        academic_year: $('#edit-aula-anio').value.trim(),
      },
    });

    getEditModal()?.hide();
    await loadClassrooms();
  } catch (err) {
    if (alertEl) {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = err.errors ? Object.values(err.errors)[0][0] : err.message;
      alertEl.classList.remove('d-none');
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Guardar cambios';
  }
});

/* ---------------- Modal Eliminar Aula ---------------- */
const deleteModalEl = $('#modal-eliminar-aula');
let deleteModalInstance = null;

function getDeleteModal() {
  if (!deleteModalInstance && deleteModalEl && window.bootstrap) {
    deleteModalInstance = new bootstrap.Modal(deleteModalEl);
  }
  return deleteModalInstance;
}

function openDeleteModal(classroomId) {
  const classroom = currentClassrooms.find((c) => String(c.id) === String(classroomId));
  if (!classroom) return;

  classroomToDeleteId = classroom.id;
  $('#eliminar-aula-nombre-text').textContent = `"${classroom.name}"`;

  const alertEl = $('#modal-delete-alert');
  if (alertEl) alertEl.classList.add('d-none');

  getDeleteModal()?.show();
}

$('#btn-confirm-delete-aula')?.addEventListener('click', async () => {
  if (!classroomToDeleteId) return;

  const btn = $('#btn-confirm-delete-aula');
  const alertEl = $('#modal-delete-alert');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Eliminando...';

  try {
    await api(`/classrooms/${classroomToDeleteId}`, { method: 'DELETE' });
    classroomToDeleteId = null;
    getDeleteModal()?.hide();
    await loadClassrooms();
  } catch (err) {
    if (alertEl) {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = err.message || 'No se pudo eliminar el aula.';
      alertEl.classList.remove('d-none');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Sí, eliminar';
  }
});

/* ---------------- Modal Lista y Progreso de Estudiantes ---------------- */
const studentsModalEl = $('#modal-estudiantes');
let studentsModalInstance = null;
let loadedClassroomStudents = [];

function getStudentsModal() {
  if (!studentsModalInstance && studentsModalEl && window.bootstrap) {
    studentsModalInstance = new bootstrap.Modal(studentsModalEl);
  }
  return studentsModalInstance;
}

function renderClassroomStudentsKpis(students) {
  const kpiBox = $('#modal-estudiantes-kpis');
  if (!kpiBox) return;

  const total = students.length;
  const withPretest = students.filter((s) => s.pretest_completed || Boolean(s.learning_track)).length;
  const completedAll = students.filter((s) => (s.progress_percent ?? 0) >= 100).length;
  const avgProgress = total > 0
    ? Math.round(students.reduce((acc, s) => acc + (s.progress_percent ?? 0), 0) / total)
    : 0;

  kpiBox.innerHTML = `
    <div class="col-6 col-lg-3">
      <div class="teacher-student-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Inscritos</span>
          <i class="bi bi-people-fill text-primary"></i>
        </div>
        <div class="h4 fw-bold mb-0" style="color:var(--edugt-text);">${total}</div>
        <small class="text-muted" style="font-size:.76rem;">Estudiantes activos</small>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="teacher-student-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Pre-test</span>
          <i class="bi bi-compass-fill text-info"></i>
        </div>
        <div class="h4 fw-bold mb-0" style="color:var(--edugt-text);">${withPretest} <span class="fs-6 text-muted fw-normal">/ ${total}</span></div>
        <small class="text-muted" style="font-size:.76rem;">Diagnóstico completado</small>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="teacher-student-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Avance Promedio</span>
          <i class="bi bi-bar-chart-fill text-success"></i>
        </div>
        <div class="h4 fw-bold mb-1" style="color:var(--edugt-primary);">${avgProgress}%</div>
        <div class="teacher-progress-track" style="height:6px;">
          <div class="teacher-progress-fill ${avgProgress >= 100 ? 'is-complete' : ''}" style="width:${avgProgress}%;"></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="teacher-student-kpi-card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="small text-muted fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.04em;">Curso 100%</span>
          <i class="bi bi-trophy-fill text-warning"></i>
        </div>
        <div class="h4 fw-bold mb-0" style="color:var(--edugt-text);">${completedAll} <span class="fs-6 text-muted fw-normal">/ ${total}</span></div>
        <small class="text-muted" style="font-size:.76rem;">3 módulos finalizados</small>
      </div>
    </div>`;
}

function renderClassroomStudentsList(students) {
  const tbody = $('#modal-estudiantes-tbody');
  if (!tbody) return;

  if (students.length === 0) {
    tbody.innerHTML = `
      <div class="text-center py-4 text-muted">
        <i class="bi bi-search fs-3 d-block mb-2"></i>
        No se encontraron estudiantes con ese criterio de búsqueda.
      </div>`;
    return;
  }

  const trackNames = {
    beginner: 'Básico',
    intermediate: 'Intermedio',
    advanced: 'Avanzado',
  };

  tbody.innerHTML = students.map((s) => {
    const initial = (s.name || 'E').trim().charAt(0).toUpperCase();
    const hasPretest = Boolean(s.pretest_completed || s.learning_track);
    const trackLabel = trackNames[s.learning_track] || null;
    const preScoreTxt = s.pretest_score !== null && s.pretest_score !== undefined ? `${s.pretest_score}%` : '';

    let pretestBadge = '';
    if (hasPretest) {
      const detail = [preScoreTxt, trackLabel].filter(Boolean).join(' · ');
      pretestBadge = `
        <span class="student-meta-pill pill-pretest-ok" title="Diagnóstico Inicial completado">
          <i class="bi bi-check-circle-fill"></i> Pre-test: ${detail || 'Completado'}
        </span>`;
    } else {
      pretestBadge = `
        <span class="student-meta-pill pill-pretest-pending" title="Aún no ha realizado el Pre-test obligatorio">
          <i class="bi bi-clock-history"></i> Pre-test: Pendiente
        </span>`;
    }

    const posttestBadge = s.posttest_completed
      ? `<span class="student-meta-pill pill-posttest-ok" title="Evaluación Final completada">
           <i class="bi bi-patch-check-fill"></i> Post-test: ${s.posttest_score ?? 0}%
         </span>`
      : '';

    const joinDate = s.joined_at ? new Date(s.joined_at).toLocaleDateString('es-GT', {
      year: 'numeric', month: 'short', day: 'numeric'
    }) : '—';

    const pct = Math.min(100, Math.max(0, s.progress_percent ?? 0));
    const doneLessons = s.completed_lessons ?? 0;
    const totalLessons = s.total_lessons ?? 12;
    const xpTotal = s.xp_total ?? 0;
    const xpLevel = s.xp_level ?? 1;
    const badgesCount = s.badges_count ?? 0;

    let stageStatusHtml = '';
    if (pct >= 100) {
      stageStatusHtml = `<span class="text-success fw-bold"><i class="bi bi-patch-check-fill me-1"></i>¡Curso completado al 100%!</span>`;
    } else if (!hasPretest) {
      stageStatusHtml = `<span class="text-warning fw-semibold"><i class="bi bi-lock-fill me-1"></i>Pendiente de realizar el Pre-test diagnóstico</span>`;
    } else if (s.current_stage) {
      const st = s.current_stage;
      stageStatusHtml = `
        <span class="fw-bold" style="color:var(--edugt-primary);">Módulo ${st.module_number}: ${esc(st.module_title)}</span>
        <span class="text-muted mx-1">·</span>
        <span style="color:var(--edugt-text);">${esc(st.step_title)}</span>`;
    } else {
      stageStatusHtml = `<span class="text-muted">En curso</span>`;
    }

    const modulesList = Array.isArray(s.modules_progress) ? s.modules_progress : [];
    const modulesBreakdownHtml = modulesList.map((m) => {
      const mPct = Math.min(100, Math.max(0, m.percent ?? 0));
      const isModDone = m.done >= m.total && m.total > 0;
      const quizTag = m.best_quiz_score !== null && m.best_quiz_score !== undefined
        ? `<span class="ms-1 ${m.quiz_passed ? 'text-success' : 'text-warning'} fw-bold" title="Mejor nota en evaluación del módulo">(${m.best_quiz_score} pts)</span>`
        : '';

      return `
        <div class="student-module-mini-box">
          <div class="d-flex align-items-center justify-content-between mb-1 gap-1">
            <span class="text-truncate fw-bold" style="font-size:.74rem;color:var(--edugt-text);" title="Módulo ${m.module_number}: ${esc(m.title)}">
              ${isModDone ? '<i class="bi bi-check-circle-fill text-success me-1"></i>' : `<i class="bi bi-layers text-primary me-1"></i>`}Mód. ${m.module_number}: ${esc(m.title)}
            </span>
            <span class="text-muted flex-shrink-0" style="font-size:.72rem;">
              <strong>${m.done}/${m.total}</strong>${quizTag}
            </span>
          </div>
          <div class="teacher-progress-track" style="height:6px;">
            <div class="teacher-progress-fill ${isModDone ? 'is-complete' : ''}" style="width:${mPct}%;"></div>
          </div>
        </div>`;
    }).join('');

    return `
      <div class="teacher-student-progress-card p-3 p-md-4">
        <div class="row g-3 align-items-center">
          <!-- Columna Izquierda: Datos del Estudiante, Pre-test, XP e Insignias -->
          <div class="col-12 col-lg-5">
            <div class="d-flex align-items-start gap-3">
              <div class="teacher-student-avatar flex-shrink-0 shadow-sm">
                ${esc(initial)}
              </div>
              <div class="flex-grow-1" style="min-width:0;">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                  <h6 class="fw-bold mb-0 text-truncate" style="color:var(--edugt-text);font-size:.98rem;">${esc(s.name)}</h6>
                  <small class="text-muted" style="font-size:.75rem;" title="Fecha de inscripción en el aula">
                    <i class="bi bi-calendar3 me-1"></i>${joinDate}
                  </small>
                </div>
                <div class="text-muted text-truncate mb-2" style="font-size:.82rem;">${esc(s.email || '—')}</div>

                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                  ${pretestBadge}
                  ${posttestBadge}
                  <span class="student-meta-pill pill-xp" title="Experiencia y nivel actual">
                    <i class="bi bi-lightning-charge-fill text-warning"></i> ${xpTotal} XP · Nv. ${xpLevel}
                  </span>
                  <span class="student-meta-pill pill-badges" title="Insignias desbloqueadas">
                    <i class="bi bi-trophy-fill text-warning"></i> ${badgesCount}/6
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Columna Derecha: Barra Horizontal de Progreso General + Desglose por Módulos -->
          <div class="col-12 col-lg-7">
            <div class="teacher-student-progress-box p-3 rounded-4">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-1.5">
                <div class="small text-truncate" style="max-width: 72%;">
                  ${stageStatusHtml}
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                  <span class="small text-muted fw-semibold">${doneLessons} de ${totalLessons} etapas</span>
                  <span class="badge rounded-pill ${pct >= 100 ? 'bg-success' : 'bg-primary'} px-2.5 py-1 fw-bold" style="font-size:.76rem;">
                    ${pct}%
                  </span>
                </div>
              </div>

              <!-- Barra horizontal principal -->
              <div class="teacher-progress-track mb-3" style="height:11px;" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100">
                <div class="teacher-progress-fill ${pct >= 100 ? 'is-complete' : ''}" style="width:${pct}%;"></div>
              </div>

              <!-- Mini barras horizontales por cada Módulo -->
              ${modulesBreakdownHtml ? `
                <div class="student-modules-grid">
                  ${modulesBreakdownHtml}
                </div>` : ''}
            </div>
          </div>
        </div>
      </div>`;
  }).join('');
}

$('#student-search-input')?.addEventListener('input', (e) => {
  const q = (e.target.value || '').trim().toLowerCase();
  if (!q) {
    renderClassroomStudentsList(loadedClassroomStudents);
    return;
  }
  const filtered = loadedClassroomStudents.filter((s) =>
    (s.name || '').toLowerCase().includes(q) ||
    (s.email || '').toLowerCase().includes(q)
  );
  renderClassroomStudentsList(filtered);
});

async function openStudentsModal(classroomId, classroomName, studentCount) {
  const modal = getStudentsModal();
  if (!modal) return;

  $('#modal-estudiantes-titulo').textContent = 'Progreso de Estudiantes';
  $('#modal-estudiantes-subtitulo').textContent = `Aula: ${classroomName}`;
  $('#modal-estudiantes-badge').textContent = `${studentCount}`;

  const searchInput = $('#student-search-input');
  if (searchInput) searchInput.value = '';

  const loadingEl = $('#modal-estudiantes-loading');
  const alertEl = $('#modal-estudiantes-alert');
  const contentEl = $('#modal-estudiantes-content');
  const tbody = $('#modal-estudiantes-tbody');

  loadingEl.classList.remove('d-none');
  alertEl.classList.add('d-none');
  contentEl.classList.add('d-none');
  tbody.innerHTML = '';

  modal.show();

  try {
    const { data } = await api(`/classrooms/${classroomId}/students`);
    const students = data.data || [];
    loadedClassroomStudents = students;

    loadingEl.classList.add('d-none');
    $('#modal-estudiantes-badge').textContent = `${students.length}`;

    if (students.length === 0) {
      alertEl.className = 'alert alert-info';
      alertEl.textContent = 'No hay estudiantes inscritos en esta aula.';
      alertEl.classList.remove('d-none');
      return;
    }

    renderClassroomStudentsKpis(students);
    renderClassroomStudentsList(students);

    contentEl.classList.remove('d-none');
  } catch (err) {
    loadingEl.classList.add('d-none');
    alertEl.className = 'alert alert-danger';
    alertEl.textContent = err.message || 'No se pudo cargar la lista de estudiantes.';
    alertEl.classList.remove('d-none');
  }
}

/* ---------------- Crear aula (RF-19) ---------------- */
$('#form-aula')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const alertM = $('#modal-alert');
  alertM.classList.add('d-none');

  try {
    await api('/classrooms', {
      method: 'POST',
      body: {
        name: $('#aula-nombre').value.trim(),
        institution: $('#aula-institucion').value.trim() || null,
        grade_level: $('#aula-grado').value.trim() || null,
        academic_year: $('#aula-anio').value.trim(),
      },
    });

    bootstrap.Modal.getInstance($('#modal-aula')).hide();
    e.target.reset();
    await loadClassrooms();
  } catch (err) {
    alertM.className = 'alert alert-danger';
    alertM.textContent = err.errors ? Object.values(err.errors)[0][0] : err.message;
    alertM.classList.remove('d-none');
  }
});

/* ---------------- Reporte PDF (RF-22, RB-12) ---------------- */
$('#form-reporte')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = $('#btn-reporte');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass"></i> Generando...';

  try {
    const classroomId = $('#reporte-aula').value;
    const res = await fetch(`${API_BASE}/reports`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${Auth.token}`,
      },
      body: JSON.stringify({
        kind: 'classroom_performance',
        classroom_id: classroomId ? Number(classroomId) : null,
      }),
    });

    if (!res.ok) {
      const j = await res.json().catch(() => null);
      throw new Error(j?.message ?? `Error ${res.status}`);
    }

    // PDF binario → descarga directa
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `edugt-reporte-${new Date().toISOString().slice(0, 10)}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    showMessage(err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-download"></i> Descargar PDF';
  }
});

/* ---------------- Logout ---------------- */
async function logout() {
  try { await api('/auth/logout', { method: 'POST' }); } catch { /* ya inválido */ }
  Auth.clear();
  window.location.href = 'index.html';
}
$('#btn-logout')?.addEventListener('click', logout);
$('#btn-logout-m')?.addEventListener('click', logout);

/* ---------------- Boot ---------------- */
(async function boot() {
  renderGreeting();

  // Sincronizar pestaña inicial
  const hash = window.location.hash.replace('#', '');
  switchTab(hash || 'aulas');

  // RB-02: docente pendiente no ve datos — aviso y fuera
  if (Auth.user.status === 'pending_approval') {
    showMessage('Tu cuenta está pendiente de aprobación administrativa. No puedes crear aulas todavía.', 'warning');
    return;
  }

  try {
    await loadClassrooms();
  } catch (err) {
    if (err.status === 401) {
      Auth.clear();
      window.location.href = 'index.html';
      return;
    }
    showMessage(err.message);
  }
})();
