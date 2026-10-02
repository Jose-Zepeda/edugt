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

function showMessage(message, kind = 'danger') {
  const el = $('#alert');
  el.className = `alert alert-${kind}`;
  el.textContent = message;
}

/* ---------------- Saludo ---------------- */
function renderGreeting() {
  const h = new Date().getHours();
  $('#greeting-kicker').textContent = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
  $('#greeting').textContent = `Docente ${Auth.user.name}`;
}

/* ---------------- Aulas (RF-19) ---------------- */
async function loadClassrooms() {
  const { data } = await api('/classrooms');

  const box = $('#classrooms');
  if (data.data.length === 0) {
    box.innerHTML = `
      <div class="col-12">
        <div class="card-edugt p-4 text-center" style="color:var(--edugt-muted)">
          <i class="bi bi-people" style="font-size:2rem;color:var(--edugt-primary)"></i>
          <p class="mt-2 mb-0">Aún no tienes aulas. Crea la primera para compartir el código con tus estudiantes.</p>
        </div>
      </div>`;
    fillSelect([]);
    return;
  }

  box.innerHTML = data.data.map((c) => `
    <div class="col-md-6 col-xl-4">
      <article class="card-edugt p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div class="stat-icon" style="background:var(--edugt-primary-soft);color:var(--edugt-primary)">
            <i class="bi bi-people-fill"></i>
          </div>
          <span class="pill-edugt ${c.is_active ? 'pill-done' : 'pill-locked'}">${c.is_active ? 'Activa' : 'Archivada'}</span>
        </div>
        <h3 class="h6 fw-bold mb-1">${esc(c.name)}</h3>
        <small style="color:var(--edugt-muted)">${esc(c.academic_year)}</small>

        <div class="border rounded-4 p-3 mt-3 d-flex align-items-center justify-content-between" style="border-color:var(--edugt-border)!important;background:#FBFAFF;">
          <div>
            <small style="color:var(--edugt-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Código</small>
            <div class="fw-bold" style="font-size:1.15rem;letter-spacing:.12em" data-code="${c.id}">${esc(c.code ?? '—')}</div>
          </div>
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-edugt" data-copy="${c.id}" title="Copiar código" aria-label="Copiar código"><i class="bi bi-clipboard"></i></button>
            <button class="btn btn-sm btn-google" data-regen="${c.id}" title="Regenerar código" aria-label="Regenerar código"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2 mt-3">
          <i class="bi bi-person-check" style="color:var(--edugt-green)"></i>
          <span class="fw-bold">${c.students_count ?? 0}</span>
          <span style="color:var(--edugt-muted)">estudiantes inscritos</span>
        </div>
      </article>
    </div>`).join('');

  fillSelect(data.data.map((c) => ({ id: c.id, name: c.name })));

  // Copiar código
  box.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    const code = box.querySelector(`[data-code="${b.dataset.copy}"]`)?.textContent.trim() ?? '';
    navigator.clipboard?.writeText(code);
    b.innerHTML = '<i class="bi bi-check-lg"></i>';
    setTimeout(() => { b.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1200);
  }));

  // Regenerar código (invalida el anterior — crit. de aceptación Fase 8)
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
}

function fillSelect(classrooms) {
  const sel = $('#reporte-aula');
  sel.innerHTML = '<option value="">Todas mis aulas</option>' +
    classrooms.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
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
