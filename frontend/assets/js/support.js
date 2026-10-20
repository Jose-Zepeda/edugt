/* ==========================================================================
   EduGT — Módulo Compartido de Soporte y Tickets (Estudiantes y Docentes)
   ========================================================================== */
'use strict';

(function () {
  let supportModalInstance = null;

  window.openSupportModal = function () {
    const modalEl = document.getElementById('modal-support');
    if (!modalEl) return;

    if (!supportModalInstance && typeof bootstrap !== 'undefined') {
      supportModalInstance = new bootstrap.Modal(modalEl);
    }

    // Reset de pestañas a "new"
    window.switchSupportModalTab('new');
    
    // Cargar historial de tickets del usuario
    window.loadMySupportTickets();

    if (supportModalInstance) {
      supportModalInstance.show();
    }
  };

  window.switchSupportModalTab = function (tab) {
    const tabNewBtn = document.getElementById('support-tab-new-btn');
    const tabMyBtn = document.getElementById('support-tab-my-btn');
    const panelNew = document.getElementById('support-tab-new');
    const panelMy = document.getElementById('support-tab-my');

    if (!tabNewBtn || !tabMyBtn || !panelNew || !panelMy) return;

    if (tab === 'new') {
      tabNewBtn.className = 'nav-link active px-3 py-2 fw-bold';
      tabMyBtn.className = 'nav-link px-3 py-2 fw-bold text-muted';
      panelNew.classList.remove('d-none');
      panelMy.classList.add('d-none');
    } else {
      tabNewBtn.className = 'nav-link px-3 py-2 fw-bold text-muted';
      tabMyBtn.className = 'nav-link active px-3 py-2 fw-bold';
      panelNew.classList.add('d-none');
      panelMy.classList.remove('d-none');
      window.loadMySupportTickets();
    }
  };

  window.loadMySupportTickets = async function () {
    const listBox = document.getElementById('support-my-list');
    const countBadge = document.getElementById('support-my-count');
    if (!listBox) return;

    try {
      const res = await api('/support/tickets/my');
      const tickets = (res.data && Array.isArray(res.data.data)) ? res.data.data : (Array.isArray(res.data) ? res.data : []);

      if (countBadge) {
        countBadge.textContent = tickets.length;
      }

      if (tickets.length === 0) {
        listBox.innerHTML = `
          <div class="text-center py-4 rounded-4" style="background:var(--edugt-card-subtle);border:1px dashed var(--edugt-border);">
            <i class="bi bi-chat-square-text text-muted" style="font-size:2rem;"></i>
            <h3 class="h6 fw-bold mt-2 mb-1">Aún no has enviado ningún ticket</h3>
            <p class="text-muted mb-0" style="font-size:.85rem;">Si encuentras algún problema o tienes una sugerencia, puedes enviarla desde la pestaña anterior.</p>
          </div>`;
        return;
      }

      const KIND_CONFIG = {
        error: { label: 'Error Técnico', icon: 'bi-bug-fill', badge: 'bg-danger-subtle text-danger border-danger-subtle' },
        suggestion: { label: 'Sugerencia', icon: 'bi-lightbulb-fill', badge: 'bg-warning-subtle text-warning border-warning-subtle' },
      };

      const STATUS_CONFIG = {
        open: { label: 'Abierto', pill: 'bg-primary-subtle text-primary border-primary-subtle' },
        in_review: { label: 'En revisión', pill: 'bg-warning-subtle text-warning border-warning-subtle' },
        resolved: { label: 'Resuelto', pill: 'bg-success-subtle text-success border-success-subtle' },
      };

      listBox.innerHTML = tickets.map((t) => {
        const kindCfg = KIND_CONFIG[t.kind] || { label: t.kind, icon: 'bi-question-circle', badge: 'bg-secondary-subtle text-secondary' };
        const statusCfg = STATUS_CONFIG[t.status] || { label: t.status, pill: 'bg-secondary-subtle text-secondary' };
        const dateFormatted = new Date(t.created_at).toLocaleDateString('es-GT', {
          day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        return `
          <div class="admin-item-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom" style="border-color:var(--edugt-border)!important;">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge rounded-pill border px-3 py-1 ${kindCfg.badge}">
                  <i class="bi ${kindCfg.icon} me-1"></i> ${kindCfg.label}
                </span>
                ${t.module ? `<span class="badge rounded-pill bg-light text-muted border px-3 py-1" style="font-size:.73rem;">${esc(t.module)}</span>` : ''}
              </div>
              <span class="badge rounded-pill border px-3 py-1 ${statusCfg.pill}">
                ${statusCfg.label}
              </span>
            </div>
            <p class="mb-2 fw-semibold" style="font-size:.92rem;color:var(--edugt-text);line-height:1.5;">
              ${esc(t.description)}
            </p>
            <div class="text-end">
              <small class="text-muted fw-semibold" style="font-size:.76rem;">
                <i class="bi bi-clock me-1"></i> Enviado el ${dateFormatted}
              </small>
            </div>
          </div>`;
      }).join('');

    } catch (err) {
      listBox.innerHTML = `<div class="alert alert-danger mb-0">${esc(err.message || 'Error al cargar tus tickets')}</div>`;
    }
  };

  window.submitSupportTicket = async function (e) {
    e.preventDefault();

    const alertBox = document.getElementById('support-alert');
    const submitBtn = document.getElementById('btn-submit-support');
    const descInput = document.getElementById('support-description');
    const moduleInput = document.getElementById('support-module');
    const kindInput = document.querySelector('input[name="support_kind"]:checked');

    if (!descInput || !submitBtn) return;

    const description = descInput.value.trim();
    if (description.length < 10) {
      if (alertBox) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'La descripción debe tener al menos 10 caracteres.';
        alertBox.classList.remove('d-none');
      }
      descInput.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Enviando...';

    try {
      await api('/support/tickets', {
        method: 'POST',
        body: {
          kind: kindInput ? kindInput.value : 'error',
          module: moduleInput ? moduleInput.value : 'general',
          description: description,
        },
      });

      if (alertBox) {
        alertBox.className = 'alert alert-success';
        alertBox.textContent = '¡Reporte enviado con éxito! El equipo administrador lo revisará.';
        alertBox.classList.remove('d-none');
      }

      descInput.value = '';
      const charCounter = document.getElementById('support-char-counter');
      if (charCounter) charCounter.textContent = '0 / 2000';

      // Cambiar a la pestaña de "Mis Tickets" tras 1.2 segundos
      setTimeout(() => {
        if (alertBox) alertBox.classList.add('d-none');
        window.switchSupportModalTab('my');
      }, 1200);

    } catch (err) {
      if (alertBox) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = err.message || 'No fue posible enviar el reporte. Inténtalo de nuevo.';
        alertBox.classList.remove('d-none');
      }
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Enviar Reporte';
    }
  };

  // Contador de caracteres en tiempo real
  document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'support-description') {
      const counter = document.getElementById('support-char-counter');
      if (counter) {
        counter.textContent = `${e.target.value.length} / 2000`;
      }
    }
  });
})();
