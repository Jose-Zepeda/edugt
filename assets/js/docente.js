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
  sel.innerHTML = '<option value="">Todas mis aulas</option>' +
    classrooms.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
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
