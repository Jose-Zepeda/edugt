/* ==========================================================================
   EduGT — Panel admin (RF-4 aprobación, RF-23 bitácora, RF-24 tickets)
   ========================================================================== */
'use strict';

Auth.load();

// Guard: solo admin
if (!Auth.token || !Auth.user) {
  window.location.href = 'index.html';
} else if (Auth.user.role !== 'admin') {
  // Arañazo simple: no-admin fuera
  window.location.href = Auth.user.role === 'teacher' ? 'docente.html' : 'estudiante.html';
}

const $ = (sel) => document.querySelector(sel);

const esc2 = esc; // helper compartido de api.js

function showAlert(msg, kind = 'danger') {
  const el = $('#alert');
  el.className = `alert alert-${kind}`;
  el.textContent = msg;
}

/* ---------------- Saludo ---------------- */
(function () {
  const h = new Date().getHours();
  $('#greeting').textContent = `Administrador ${Auth.user.name.split(' ')[0]}`;
})();

/* ---------------- Docentes pendientes (RF-4) ---------------- */
async function loadPendingTeachers() {
  const { data } = await api('/admin/teachers?status=pending');

  const box = $('#pending-teachers');

  if (data.total === 0) {
    box.innerHTML = '<p class="mb-0" style="color:var(--edugt-muted)">No hay docentes pendientes. Todas las cuentas están resueltas.</p>';
    return;
  }

  box.innerHTML = data.data.map((t) => `
    <div class="d-flex align-items-center justify-content-between border rounded-4 px-3 py-2" style="border-color:var(--edugt-border)!important;">
      <div class="d-flex align-items-center gap-3">
        <div class="stat-icon" style="background:var(--edugt-amber-soft);color:var(--edugt-amber)">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <div class="fw-bold">${esc2(t.name)}</div>
          <small style="color:var(--edugt-muted)">${esc2(t.email)}</small>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-edugt" data-approve="${t.id}"><i class="bi bi-check-lg"></i> Aprobar</button>
        <button class="btn btn-sm btn-google" data-reject="${t.id}"><i class="bi bi-x-lg"></i> Rechazar</button>
      </div>
    </div>`).join('');

  box.querySelectorAll('[data-approve]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    try {
      await api(`/admin/teachers/${b.dataset.approve}/approve`, { method: 'POST' });
      await loadPendingTeachers();
      await loadAuditLogs(); // la acción quedó en la bitácora
      showAlert('Docente aprobado. Ya puede iniciar sesión y crear aulas.', 'success');
    } catch (err) {
      showAlert(err.message);
      b.disabled = false;
    }
  }));

  box.querySelectorAll('[data-reject]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    try {
      await api(`/admin/teachers/${b.dataset.reject}/reject`, { method: 'POST' });
      await loadPendingTeachers();
      await loadAuditLogs();
      showAlert('Docente rechazado.', 'info');
    } catch (err) {
      showAlert(err.message);
      b.disabled = false;
    }
  }));
}

/* ---------------- Tickets (RF-24) ---------------- */
let ticketStatusFilter = '';

async function loadTickets() {
  const qs = ticketStatusFilter ? `?status=${ticketStatusFilter}` : '';
  const { data } = await api(`/admin/support-tickets${qs}`);

  const box = $('#tickets');

  if (data.data.length === 0) {
    box.innerHTML = '<p class="mb-0" style="color:var(--edugt-muted)">Sin tickets en este estado.</p>';
    return;
  }

  const KIND_LABEL = { error: 'Error reportado', suggestion: 'Sugerencia didáctica' };
  const STATUS_LABEL = { open: 'Abierto', in_review: 'En revisión', resolved: 'Resuelto' };
  const STATUS_PILL = { open: 'pill-now', in_review: 'pill-done', resolved: 'pill-locked' };

  box.innerHTML = data.data.map((t) => `
    <div class="d-flex align-items-start justify-content-between border rounded-4 px-3 py-2 flex-wrap gap-2" style="border-color:var(--edugt-border)!important;">
      <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi ${t.kind === 'error' ? 'bi-bug' : 'bi-lightbulb'}" style="color:${t.kind === 'error' ? '#B91C1C' : 'var(--edugt-amber)'}"></i>
          <strong>${KIND_LABEL[t.kind] ?? t.kind}</strong>
          ${(t.reporter?.name) ? `<small style="color:var(--edugt-muted)">— ${esc2(t.reporter.name)}</small>` : ''}
          <span class="pill-edugt ${STATUS_PILL[t.status] ?? 'pill-locked'}">${STATUS_LABEL[t.status] ?? t.status}</span>
        </div>
        <div style="font-size:.9rem;">${esc2(t.description)}</div>
      </div>
      <div class="d-flex gap-1 align-items-center">
        <select class="form-control form-control-edugt" style="padding:.3rem .5rem;font-size:.8rem" data-ticket-status="${t.id}" aria-label="Cambiar estado del ticket">
          <option value="open" ${t.status === 'open' ? 'selected' : ''}>Abierto</option>
          <option value="in_review" ${t.status === 'in_review' ? 'selected' : ''}>En revisión</option>
          <option value="resolved" ${t.status === 'resolved' ? 'selected' : ''}>Resuelto</option>
        </select>
      </div>
    </div>`).join('');

  box.querySelectorAll('[data-ticket-status]').forEach((sel) => sel.addEventListener('change', async () => {
    try {
      await api(`/admin/support-tickets/${sel.dataset.ticketStatus}/status`, {
        method: 'POST',
        body: { status: sel.value },
      });
      await loadAuditLogs();
      showAlert('Estado actualizado y registrado en la bitácora.', 'success');
    } catch (err) {
      showAlert(err.message);
      await loadTickets();
    }
  }));
}

$('#ticket-filters')?.addEventListener('click', (e) => {
  const b = e.target.closest('[data-status]');
  if (!b) return;
  ticketStatusFilter = b.dataset.status;

  $('#ticket-filters').querySelectorAll('button').forEach((x) => {
    x.className = 'btn btn-sm btn-google';
  });
  b.className = 'btn btn-sm btn-edugt';

  loadTickets();
});

/* ---------------- Bitácora (RF-23) ---------------- */
async function loadAuditLogs() {
  const { data } = await api('/admin/audit-logs?limit=15');

  const box = $('#audit-logs');

  if (data.data.length === 0) {
    box.innerHTML = '<p class="mb-0" style="color:var(--edugt-muted)">Aún no hay acciones registradas.</p>';
    return;
  }

  const ACTION_LABEL = {
    'teacher.approved': 'Docente aprobado',
    'teacher.rejected': 'Docente rechazado',
    'support.status_changed': 'Estado de ticket cambiado',
  };

  box.innerHTML = data.data.map((l) => `
    <div class="d-flex align-items-center justify-content-between border-bottom pb-1" style="border-color:var(--edugt-border)!important;font-size:.85rem;">
      <div>
        <i class="bi bi-dot"></i>
        <strong>${ACTION_LABEL[l.action] ?? esc2(l.action)}</strong>
        ${l.user?.name ? `<span style="color:var(--edugt-muted)">— ${esc2(l.user.name)}</span>` : ''}
      </div>
      <small style="color:var(--edugt-muted)">${new Date(l.created_at).toLocaleString('es-GT')}</small>
    </div>`).join('');
}

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
  try {
    await Promise.all([loadPendingTeachers(), loadTickets(), loadAuditLogs()]);
  } catch (err) {
    if (err.status === 401) {
      Auth.clear();
      window.location.href = 'index.html';
      return;
    }
    if (err.status === 403) {
      showAlert('Tu cuenta no tiene permisos de administrador.');
      return;
    }
    showAlert(err.message);
  }
})();
