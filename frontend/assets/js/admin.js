/* ==========================================================================
   EduGT — Panel de Administración
   4 Interfaces Dedicadas: Resumen, Docentes, Soporte y Bitácora
   ========================================================================== */
'use strict';

Auth.load();

// Guard de autenticación y rol exclusivo de admin
if (!Auth.token || !Auth.user) {
  window.location.href = 'index.html';
} else if (Auth.user.role !== 'admin') {
  window.location.href = Auth.user.role === 'teacher' ? 'docente.html' : 'estudiante.html';
}

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);
const esc2 = esc; // helper de api.js

function showAlert(msg, kind = 'danger') {
  const el = $('#alert');
  if (!el) return;
  el.className = `alert alert-${kind} alert-dismissible fade show shadow-xs mb-3`;
  el.innerHTML = `
    <div class="d-flex align-items-center justify-content-between">
      <div>${esc2(msg)}</div>
      <button type="button" class="btn-close" aria-label="Cerrar" onclick="this.closest('.alert').classList.add('d-none')"></button>
    </div>`;
  el.classList.remove('d-none');
  setTimeout(() => {
    if (el && !el.classList.contains('d-none')) {
      el.classList.add('d-none');
    }
  }, 5000);
}

/* ---------------- Tema Claro / Oscuro ---------------- */
function initTheme() {
  const saved = localStorage.getItem('edugt_theme') || localStorage.getItem('edugt-theme') || 'light';
  applyTheme(saved);

  const toggleDesktop = $('#theme-toggle-desktop');
  const toggleMobile = $('#theme-toggle-mobile');

  const onToggle = () => {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = cur === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  };

  toggleDesktop?.addEventListener('click', onToggle);
  toggleMobile?.addEventListener('click', onToggle);
}

function applyTheme(theme) {
  if (theme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
  localStorage.setItem('edugt_theme', theme);
  localStorage.setItem('edugt-theme', theme);

  const isDark = theme === 'dark';
  const iconD = $('#theme-icon-desktop');
  const iconM = $('#theme-icon-mobile');

  if (iconD) iconD.className = isDark ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill';
  if (iconM) iconM.className = isDark ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill';
}

/* ---------------- Fecha y Saludo ---------------- */
function initHeader() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const dateStr = now.toLocaleDateString('es-GT', options);
  if ($('#admin-current-date')) {
    $('#admin-current-date').textContent = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
  }

  const firstName = (Auth.user.name || 'Admin').split(' ')[0];
  if ($('#greeting')) {
    $('#greeting').textContent = `Hola, ${firstName}`;
  }
  if ($('#sidebar-admin-name')) {
    $('#sidebar-admin-name').textContent = Auth.user.name || 'Administrador';
  }
}

/* ---------------- Navegación de Pestañas (SPA) ---------------- */
const VALID_TABS = ['resumen', 'docentes', 'soporte', 'bitacora'];
let currentTab = 'resumen';

window.switchAdminTab = function(tabName) {
  if (!VALID_TABS.includes(tabName)) tabName = 'resumen';
  currentTab = tabName;

  // Actualizar estado en Sidebar & Bottom Nav
  $$('#admin-nav-tabs [data-tab-target], .bottom-nav [data-tab-target]').forEach((btn) => {
    const match = btn.dataset.tabTarget === tabName;
    btn.classList.toggle('active', match);
  });

  // Mostrar panel correspondiente y ocultar los demás
  $$('.admin-tab-panel').forEach((panel) => {
    panel.classList.toggle('d-none', panel.id !== `tab-${tabName}`);
  });

  window.location.hash = `#${tabName}`;
  window.scrollTo({ top: 0, behavior: 'smooth' });

  // Carga reactiva de datos según la pestaña activa
  if (tabName === 'resumen') {
    loadMetrics();
  } else if (tabName === 'docentes') {
    loadTeachersData();
  } else if (tabName === 'soporte') {
    loadTickets();
  } else if (tabName === 'bitacora') {
    loadAuditLogs();
  }
};

window.addEventListener('hashchange', () => {
  const hash = window.location.hash.replace('#', '');
  if (VALID_TABS.includes(hash) && hash !== currentTab) {
    window.switchAdminTab(hash);
  }
});

/* ==========================================================================
   1. RESUMEN Y MÉTRICAS
   ========================================================================== */
async function loadMetrics() {
  try {
    const res = await api('/admin/metrics');
    const data = (res.data && res.data.teachers) ? res.data : (res.data?.data || {});

    // KPI: Docentes pendientes
    const pendingCount = data.teachers?.pending || 0;
    const activeTeachersCount = data.teachers?.active || 0;
    $('#kpi-teachers-pending').textContent = pendingCount;
    if ($('#kpi-teachers-alert')) {
      $('#kpi-teachers-alert').classList.toggle('d-none', pendingCount === 0);
    }
    const navBadgeT = $('#nav-badge-teachers');
    if (navBadgeT) {
      navBadgeT.textContent = pendingCount;
      navBadgeT.classList.toggle('d-none', pendingCount === 0);
    }
    if ($('#subtab-badge-pending')) $('#subtab-badge-pending').textContent = pendingCount;
    if ($('#subtab-badge-active')) $('#subtab-badge-active').textContent = activeTeachersCount;

    // KPI: Docentes activos
    $('#kpi-teachers-active').textContent = activeTeachersCount;

    // KPI: Estudiantes activos
    $('#kpi-students-active').textContent = data.students?.active || 0;
    if ($('#kpi-classrooms-info')) {
      const aulasCount = data.classrooms?.active || 0;
      $('#kpi-classrooms-info').textContent = `${aulasCount} ${aulasCount === 1 ? 'aula creada' : 'aulas creadas'}`;
    }

    // KPI: Tickets de soporte
    const openTicketsCount = data.tickets?.open || 0;
    const inReviewCount = data.tickets?.in_review || 0;
    const resolvedCount = data.tickets?.resolved || 0;
    $('#kpi-tickets-open').textContent = openTicketsCount;
    if ($('#kpi-tickets-sub')) {
      $('#kpi-tickets-sub').textContent = `${inReviewCount} en revisión`;
    }
    if ($('#kpi-tickets-resolved-info')) {
      $('#kpi-tickets-resolved-info').textContent = `${resolvedCount} resueltos`;
    }
    const navBadgeTick = $('#nav-badge-tickets');
    if (navBadgeTick) {
      const activeTickets = openTicketsCount + inReviewCount;
      navBadgeTick.textContent = activeTickets;
      navBadgeTick.classList.toggle('d-none', activeTickets === 0);
    }

    // Mini feed de docentes pendientes en resumen
    await loadResumenPendingTeachers();

    // Mini feed de bitácora reciente
    renderResumenAuditMini(data.recent_logs || []);

  } catch (err) {
    console.error('Error cargando métricas:', err);
  }
}

async function loadResumenPendingTeachers() {
  const box = $('#resumen-pending-teachers-list');
  if (!box) return;

  try {
    const { data } = await api('/admin/teachers?status=pending');
    if (data.total === 0) {
      box.innerHTML = `
        <div class="text-center py-4 px-3 rounded-4" style="background:var(--edugt-card-subtle);border:1.5px dashed var(--edugt-border);">
          <i class="bi bi-patch-check-fill text-success" style="font-size:2.1rem;"></i>
          <p class="mb-1 mt-2 fw-bold" style="font-size:.95rem;">¡Todo al día!</p>
          <small class="text-muted">No hay docentes pendientes de aprobación.</small>
        </div>`;
      return;
    }

    box.innerHTML = data.data.slice(0, 4).map((t) => `
      <div class="admin-item-card d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon" style="width:42px;height:42px;background:var(--edugt-amber-soft);color:var(--edugt-amber);">
            <i class="bi bi-person-fill"></i>
          </div>
          <div>
            <div class="fw-bold" style="font-size:.94rem;">${esc2(t.name)}</div>
            <small class="text-muted">${esc2(t.email)}</small>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-edugt py-1 px-3 rounded-pill" onclick="window.approveTeacherDirect(${t.id})">
            <i class="bi bi-check-lg me-1"></i> Aprobar
          </button>
          <button class="btn btn-sm btn-outline-danger py-1 px-3 rounded-pill" onclick="window.rejectTeacherDirect(${t.id})">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>`).join('');
  } catch (err) {
    box.innerHTML = `<p class="text-danger mb-0">${esc2(err.message)}</p>`;
  }
}

function renderResumenAuditMini(logs) {
  const box = $('#resumen-audit-mini-feed');
  if (!box) return;

  if (logs.length === 0) {
    box.innerHTML = `
      <div class="text-center py-4 px-3 rounded-4" style="background:var(--edugt-card-subtle);border:1.5px dashed var(--edugt-border);">
        <p class="text-muted mb-0">Sin actividad reciente registrada.</p>
      </div>`;
    return;
  }

  const ACTION_LABEL = {
    'teacher.approved': { label: 'Docente aprobado', icon: 'bi-check-circle-fill', bg: 'var(--edugt-green-soft)', color: 'var(--edugt-green)' },
    'teacher.rejected': { label: 'Docente rechazado', icon: 'bi-x-circle-fill', bg: 'var(--edugt-danger-soft)', color: 'var(--edugt-danger)' },
    'support.status_changed': { label: 'Ticket de soporte actualizado', icon: 'bi-arrow-repeat', bg: 'var(--edugt-primary-soft)', color: 'var(--edugt-primary)' },
  };

  box.innerHTML = logs.map((l) => {
    const act = ACTION_LABEL[l.action] || { label: l.action, icon: 'bi-dot', bg: 'var(--edugt-card)', color: 'var(--edugt-muted)' };
    const dateFormatted = new Date(l.created_at).toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' });
    return `
      <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded-3 border" style="border-color:var(--edugt-border)!important;background:var(--edugt-card-subtle);">
        <div class="d-flex align-items-center gap-3 overflow-hidden">
          <div class="stat-icon" style="width:34px;height:34px;border-radius:10px;background:${act.bg};color:${act.color};font-size:.95rem;flex-shrink:0;">
            <i class="bi ${act.icon}"></i>
          </div>
          <div class="overflow-hidden">
            <div class="fw-bold text-truncate" style="font-size:.86rem;">${act.label}</div>
            <small class="text-muted text-truncate d-block" style="font-size:.75rem;">Por ${esc2(l.user?.name || 'Admin EduGT')}</small>
          </div>
        </div>
        <span class="badge rounded-pill bg-light text-muted border px-2 py-1 ms-2 text-nowrap" style="font-size:.74rem;">${dateFormatted}</span>
      </div>`;
  }).join('');
}

/* ==========================================================================
   2. DOCENTES (PENDIENTES Y ACTIVOS)
   ========================================================================== */
let teacherSubtab = 'pending';

window.switchTeacherSubtab = function(tab) {
  teacherSubtab = tab;
  $('#btn-subtab-teachers-pending').className = `btn btn-sm rounded-pill px-3 py-1 ${tab === 'pending' ? 'btn-edugt' : 'btn-google border-0'}`;
  $('#btn-subtab-teachers-active').className = `btn btn-sm rounded-pill px-3 py-1 ${tab === 'active' ? 'btn-edugt' : 'btn-google border-0'}`;

  $('#subtab-content-pending').classList.toggle('d-none', tab !== 'pending');
  $('#subtab-content-active').classList.toggle('d-none', tab !== 'active');

  loadTeachersData();
};

async function loadTeachersData() {
  if (teacherSubtab === 'pending') {
    await loadPendingTeachersList();
  } else {
    await loadActiveTeachersList();
  }
}

async function loadPendingTeachersList() {
  const box = $('#pending-teachers-container');
  if (!box) return;

  try {
    const { data } = await api('/admin/teachers?status=pending');
    $('#subtab-badge-pending').textContent = data.total;

    if (data.total === 0) {
      box.innerHTML = `
        <div class="text-center py-5 px-3 rounded-4" style="background:var(--edugt-card-subtle);border:1.5px dashed var(--edugt-border);">
          <div class="stat-icon mx-auto mb-3" style="width:58px;height:58px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.8rem;">
            <i class="bi bi-shield-check"></i>
          </div>
          <h3 class="h6 fw-bold mb-1">¡No hay solicitudes pendientes!</h3>
          <p class="text-muted mb-0" style="font-size:.88rem;">Todas las cuentas docentes se encuentran aprobadas y al día.</p>
        </div>`;
      return;
    }

    box.innerHTML = data.data.map((t) => `
      <div class="admin-item-card d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon" style="width:48px;height:48px;background:var(--edugt-amber-soft);color:var(--edugt-amber);font-size:1.3rem;">
            <i class="bi bi-person-fill-exclamation"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <h3 class="fw-bold mb-0" style="font-size:1.02rem;">${esc2(t.name)}</h3>
              <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1" style="font-size:.73rem;">Pendiente</span>
            </div>
            <div class="text-muted mb-1" style="font-size:.86rem;">${esc2(t.email)}</div>
            <small class="text-muted" style="font-size:.76rem;">
              <i class="bi bi-clock me-1"></i> Registrado el ${new Date(t.created_at).toLocaleDateString('es-GT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </small>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-edugt rounded-pill px-3 py-2 fw-bold" onclick="window.approveTeacherDirect(${t.id})">
            <i class="bi bi-check-circle-fill me-1"></i> Aprobar Docente
          </button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2" onclick="window.rejectTeacherDirect(${t.id})">
            <i class="bi bi-x-circle me-1"></i> Rechazar
          </button>
        </div>
      </div>`).join('');

  } catch (err) {
    box.innerHTML = `<div class="alert alert-danger">${esc2(err.message)}</div>`;
  }
}

async function loadActiveTeachersList() {
  const box = $('#active-teachers-container');
  if (!box) return;

  try {
    const { data } = await api('/admin/teachers?status=active');
    $('#subtab-badge-active').textContent = data.total;

    if (data.total === 0) {
      box.innerHTML = '<p class="text-muted text-center py-4">No hay docentes activos registrados.</p>';
      return;
    }

    box.innerHTML = data.data.map((t) => `
      <div class="admin-item-card d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon" style="width:44px;height:44px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.15rem;">
            <i class="bi bi-person-check-fill"></i>
          </div>
          <div>
            <div class="fw-bold" style="font-size:.96rem;">${esc2(t.name)}</div>
            <div class="text-muted" style="font-size:.84rem;">${esc2(t.email)}</div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1" style="font-size:.76rem;">
            <i class="bi bi-check-lg me-1"></i> Activo
          </span>
          <small class="text-muted fw-semibold" style="font-size:.8rem;">
            Alta: ${new Date(t.created_at).toLocaleDateString('es-GT', { day: '2-digit', month: 'short', year: 'numeric' })}
          </small>
        </div>
      </div>`).join('');

  } catch (err) {
    box.innerHTML = `<div class="alert alert-danger">${esc2(err.message)}</div>`;
  }
}

window.approveTeacherDirect = async function(teacherId) {
  try {
    await api(`/admin/teachers/${teacherId}/approve`, { method: 'POST' });
    showAlert('Docente aprobado con éxito. La cuenta ya puede ingresar.', 'success');
    await loadMetrics();
    await loadTeachersData();
  } catch (err) {
    showAlert(err.message || 'Error al aprobar docente');
  }
};

window.rejectTeacherDirect = async function(teacherId) {
  if (!confirm('¿Estás seguro de que deseas rechazar esta solicitud de docente?')) return;
  try {
    await api(`/admin/teachers/${teacherId}/reject`, { method: 'POST' });
    showAlert('Solicitud de docente rechazada.', 'info');
    await loadMetrics();
    await loadTeachersData();
  } catch (err) {
    showAlert(err.message || 'Error al rechazar docente');
  }
};

/* ==========================================================================
   3. SOPORTE (TICKETS DE ESTUDIANTES Y DOCENTES)
   ========================================================================== */
let ticketStatusFilter = '';
let ticketKindFilter = '';

window.loadTickets = async function() {
  const box = $('#tickets');
  if (!box) return;

  const params = new URLSearchParams();
  if (ticketStatusFilter) params.set('status', ticketStatusFilter);
  const qs = params.toString() ? `?${params.toString()}` : '';

  try {
    const { data } = await api(`/admin/support-tickets${qs}`);
    let tickets = data.data || [];

    // Filtro en cliente por Tipo (error / suggestion)
    if (ticketKindFilter) {
      tickets = tickets.filter(t => t.kind === ticketKindFilter);
    }

    if (tickets.length === 0) {
      box.innerHTML = `
        <div class="text-center py-5 px-3 rounded-4" style="background:var(--edugt-card-subtle);border:1.5px dashed var(--edugt-border);">
          <i class="bi bi-inbox text-muted" style="font-size:2.2rem;"></i>
          <h3 class="h6 fw-bold mt-2 mb-1">Sin tickets para los filtros seleccionados</h3>
          <p class="text-muted mb-0" style="font-size:.86rem;">Prueba cambiando el estado o tipo en la barra superior.</p>
        </div>`;
      return;
    }

    const KIND_CONFIG = {
      error: { label: 'Error Técnico', icon: 'bi-bug-fill', badgeClass: 'bg-danger-subtle text-danger border-danger-subtle' },
      suggestion: { label: 'Sugerencia', icon: 'bi-lightbulb-fill', badgeClass: 'bg-warning-subtle text-warning border-warning-subtle' },
    };

    const STATUS_CONFIG = {
      open: { label: 'Abierto', pillClass: 'bg-primary-subtle text-primary border-primary-subtle' },
      in_review: { label: 'En revisión', pillClass: 'bg-warning-subtle text-warning border-warning-subtle' },
      resolved: { label: 'Resuelto', pillClass: 'bg-success-subtle text-success border-success-subtle' },
    };

    box.innerHTML = tickets.map((t) => {
      const kindCfg = KIND_CONFIG[t.kind] || { label: t.kind, icon: 'bi-question-circle', badgeClass: 'bg-secondary-subtle text-secondary' };
      const statusCfg = STATUS_CONFIG[t.status] || { label: t.status, pillClass: 'bg-secondary-subtle text-secondary' };
      const reporterRole = t.reporter?.role === 'teacher' ? 'Docente' : (t.reporter?.role === 'student' ? 'Estudiante' : 'Usuario');
      const roleBadgeClass = t.reporter?.role === 'teacher' ? 'bg-purple-subtle text-purple border-purple-subtle' : 'bg-primary-subtle text-primary border-primary-subtle';

      return `
        <div class="admin-item-card">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom" style="border-color:var(--edugt-border)!important;">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge rounded-pill border px-3 py-1 ${kindCfg.badgeClass}">
                <i class="bi ${kindCfg.icon} me-1"></i> ${kindCfg.label}
              </span>
              <span class="badge rounded-pill border px-3 py-1 ${roleBadgeClass}" style="font-size:.74rem;">
                ${reporterRole}
              </span>
              ${t.module ? `<span class="badge rounded-pill bg-light text-muted border px-3 py-1" style="font-size:.74rem;">Módulo: ${esc2(t.module)}</span>` : ''}
              <span class="badge rounded-pill border px-3 py-1 ${statusCfg.pillClass}">
                ${statusCfg.label}
              </span>
            </div>
            
            <small class="text-muted fw-semibold" style="font-size:.78rem;">
              <i class="bi bi-calendar3 me-1"></i> ${new Date(t.created_at).toLocaleDateString('es-GT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </small>
          </div>

          <div class="mb-3">
            <p class="mb-2 fw-semibold" style="font-size:.96rem;color:var(--edugt-text);line-height:1.5;">
              ${esc2(t.description)}
            </p>
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:.82rem;">
              <i class="bi bi-person-circle"></i>
              <span>Reportado por: <strong>${esc2(t.reporter?.name || 'Usuario desconocido')}</strong> (${esc2(t.reporter?.email || '—')})</span>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between pt-2 border-top flex-wrap gap-2" style="border-color:var(--edugt-border)!important;">
            <span class="text-muted fw-bold" style="font-size:.8rem;">Actualizar estado:</span>
            <div class="d-flex align-items-center gap-2">
              <select class="form-select form-select-sm form-control-edugt py-1 px-3 rounded-pill" style="font-size:.84rem;width:auto;" onchange="window.updateTicketStatusDirect(${t.id}, this.value)">
                <option value="open" ${t.status === 'open' ? 'selected' : ''}>Marcar como Abierto</option>
                <option value="in_review" ${t.status === 'in_review' ? 'selected' : ''}>Marcar como En revisión</option>
                <option value="resolved" ${t.status === 'resolved' ? 'selected' : ''}>Marcar como Resuelto</option>
              </select>
            </div>
          </div>
        </div>`;
    }).join('');

  } catch (err) {
    box.innerHTML = `<div class="alert alert-danger">${esc2(err.message)}</div>`;
  }
};

window.updateTicketStatusDirect = async function(ticketId, newStatus) {
  try {
    await api(`/admin/support-tickets/${ticketId}/status`, {
      method: 'POST',
      body: { status: newStatus },
    });
    showAlert('Estado del ticket actualizado y registrado en la bitácora.', 'success');
    await loadMetrics();
    await window.loadTickets();
  } catch (err) {
    showAlert(err.message || 'Error al cambiar estado del ticket');
    await window.loadTickets();
  }
};

// Listeners de filtros de tickets
$('#ticket-status-filters')?.addEventListener('click', (e) => {
  const b = e.target.closest('[data-status]');
  if (!b) return;
  ticketStatusFilter = b.dataset.status;

  $('#ticket-status-filters').querySelectorAll('button').forEach((x) => {
    x.className = 'btn btn-sm btn-google rounded-pill px-3 py-1';
  });
  b.className = 'btn btn-sm btn-edugt rounded-pill px-3 py-1';

  window.loadTickets();
});

$('#select-ticket-kind')?.addEventListener('change', (e) => {
  ticketKindFilter = e.target.value;
  window.loadTickets();
});

/* ==========================================================================
   4. BITÁCORA (AUDITORÍA Y SEGURIDAD)
   ========================================================================== */
window.loadAuditLogs = async function() {
  const tbody = $('#audit-logs-table-body');
  if (!tbody) return;

  try {
    const { data } = await api('/admin/audit-logs?limit=50');
    const logs = data.data || [];

    if (logs.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Aún no hay acciones registradas en la bitácora.</td></tr>`;
      return;
    }

    const ACTION_MAP = {
      'teacher.approved': { label: 'Docente aprobado', badge: 'bg-success-subtle text-success border-success-subtle' },
      'teacher.rejected': { label: 'Docente rechazado', badge: 'bg-danger-subtle text-danger border-danger-subtle' },
      'support.status_changed': { label: 'Estado de ticket cambiado', badge: 'bg-primary-subtle text-primary border-primary-subtle' },
    };

    tbody.innerHTML = logs.map((l) => {
      const act = ACTION_MAP[l.action] || { label: l.action, badge: 'bg-secondary-subtle text-secondary' };
      const dateFormatted = new Date(l.created_at).toLocaleString('es-GT', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });

      let contextStr = '—';
      if (l.context) {
        if (typeof l.context === 'object') {
          contextStr = Object.entries(l.context).map(([k, v]) => `<span class="badge bg-light text-muted border px-2 py-1 me-1">${esc2(k)}: ${esc2(String(v))}</span>`).join('');
        } else {
          contextStr = esc2(String(l.context));
        }
      }

      return `
        <tr>
          <td class="py-3 px-3">
            <span class="badge rounded-pill border px-3 py-1 ${act.badge}">
              ${act.label}
            </span>
          </td>
          <td class="py-3 px-3">
            <div class="fw-bold">${esc2(l.user?.name || 'Sistema')}</div>
            <small class="text-muted">${esc2(l.user?.email || '—')}</small>
          </td>
          <td class="py-3 px-3">${contextStr}</td>
          <td class="py-3 px-3">
            <code class="text-muted" style="font-size:.8rem;">${esc2(l.ip || '127.0.0.1')}</code>
          </td>
          <td class="py-3 px-3 text-end text-muted" style="font-size:.82rem;">${dateFormatted}</td>
        </tr>`;
    }).join('');

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-4">${esc2(err.message)}</td></tr>`;
  }
};

/* ---------------- Logout ---------------- */
async function logout() {
  try { await api('/auth/logout', { method: 'POST' }); } catch { /* ya inválido */ }
  Auth.clear();
  window.location.href = 'index.html';
}

$('#btn-logout')?.addEventListener('click', logout);
$('#btn-logout-m')?.addEventListener('click', logout);

/* ---------------- Inicialización ---------------- */
(async function boot() {
  initTheme();
  initHeader();

  const initialTab = window.location.hash.replace('#', '') || 'resumen';
  window.switchAdminTab(initialTab);
})();
