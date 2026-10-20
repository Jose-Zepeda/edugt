/* ==========================================================================
   EduGT — Vista de lección interactiva (RF-9, RF-10)
   Markdown sanitizado con DOMPurify.
   El cronómetro mide tiempo real en página; el backend otorga el XP (RB-10).
   ========================================================================== */
'use strict';

Auth.load();
if (!Auth.token) {
  window.location.href = 'index.html';
}

const $ = (sel) => document.querySelector(sel);
const lessonId = new URLSearchParams(window.location.search).get('id');

// Cronómetro de tiempo invertido
let segundosEnPagina = 0;
const clock = setInterval(() => { segundosEnPagina++; }, 1000);

// Guardar tiempo al salir
window.addEventListener('beforeunload', () => {
  if (segundosEnPagina > 5 && lessonId) {
    navigator.sendBeacon?.(
      `${API_BASE}/lessons/${lessonId}/complete`,
      new Blob([JSON.stringify({ time_spent_seconds: segundosEnPagina })], { type: 'application/json' })
    );
  }
});

function showAlert(msg) {
  const el = $('#alert');
  if (el) {
    el.className = 'alert alert-danger';
    el.textContent = msg;
    el.classList.remove('d-none');
  }
}

/* ---------------- Render de la Lección ---------------- */
async function boot() {
  if (!lessonId) {
    $('#leccion-card').innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-exclamation-triangle text-warning fs-1 mb-2"></i>
        <p class="mb-0 text-muted">No se especificó ninguna lección para cargar.</p>
        <a href="estudiante.html" class="btn btn-edugt mt-3">Volver a mi ruta</a>
      </div>`;
    return;
  }

  try {
    const { data } = await api(`/lessons/${lessonId}`);
    const l = data.data;

    // Actualizar badge de módulo
    if ($('#lesson-track-badge')) {
      $('#lesson-track-badge').innerHTML = `<i class="bi bi-journal-code text-primary me-1"></i> Lección interactiva · ${l.estimated_minutes} min`;
    }

    // Markdown sanitizado
    const htmlContent = DOMPurify.sanitize(marked.parse(l.content_markdown ?? ''));

    // Bloque de Pseudocódigo
    let pseudocodeHtml = '';
    if (l.pseudocode_json) {
      let codeText = '';
      let codeExplanation = '';

      if (typeof l.pseudocode_json === 'object') {
        codeText = l.pseudocode_json.codigo || l.pseudocode_json.ejemplo || JSON.stringify(l.pseudocode_json, null, 2);
        codeExplanation = l.pseudocode_json.explicacion || '';
      } else {
        codeText = String(l.pseudocode_json);
      }

      pseudocodeHtml = `
        <div class="code-window">
          <div class="code-window-header">
            <div class="d-flex align-items-center gap-2">
              <div class="window-dots">
                <span class="window-dot dot-red"></span>
                <span class="window-dot dot-yellow"></span>
                <span class="window-dot dot-green"></span>
              </div>
              <span class="ms-2"><i class="bi bi-terminal me-1"></i> Pseudocódigo de Ejemplo</span>
            </div>
            <button type="button" class="btn btn-sm btn-dark text-light border-0 px-2 py-0" id="btn-copy-code" title="Copiar pseudocódigo">
              <i class="bi bi-clipboard me-1"></i> Copiar
            </button>
          </div>
          <pre class="code-window-body"><code>${esc(codeText)}</code></pre>
          ${codeExplanation ? `
            <div class="code-explanation-box">
              <i class="bi bi-info-circle-fill text-primary me-1"></i>
              <strong>¿Cómo funciona?</strong> ${esc(codeExplanation)}
            </div>` : ''}
        </div>`;
    }

    // Bloque de Reto Interactivo
    let retoHtml = '';
    if (l.pseudocode_json && l.pseudocode_json.reto) {
      const r = l.pseudocode_json.reto;
      const questionHtml = DOMPurify.sanitize(marked.parse(r.pregunta ?? ''));
      retoHtml = `
        <div id="lesson-challenge-box" class="card-edugt p-4 mb-4 border-2 mt-4" style="border: 2px solid ${l.completed ? 'var(--edugt-green)' : 'var(--edugt-primary)'}!important; background: ${l.completed ? 'rgba(16, 185, 129, 0.04)' : 'var(--edugt-bg-card)'};">
          <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <div class="stat-icon" style="width:40px;height:40px;background:${l.completed ? 'var(--edugt-green-soft)' : 'var(--edugt-primary-soft)'};color:${l.completed ? 'var(--edugt-green)' : 'var(--edugt-primary)'};font-size:1.2rem;border-radius:12px;">
                <i class="bi bi-puzzle-fill"></i>
              </div>
              <div>
                <h3 class="h6 fw-bold mb-0">Reto de Comprensión</h3>
                <small class="text-muted">Resuelve este acertijo para comprobar tu aprendizaje y activar la recompensa (+${l.xp_reward} XP).</small>
              </div>
            </div>
            ${l.completed ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Reto Superado</span>' : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill"><i class="bi bi-question-circle me-1"></i> Desafío Activo</span>'}
          </div>

          <div class="p-3 mb-3 rounded-3 lesson-markdown lesson-challenge-prompt" style="font-size:.98rem;">
            ${questionHtml}
          </div>

          <!-- Opciones interactivas -->
          <div class="d-flex flex-column gap-2 mb-3" id="challenge-options-list">
            ${r.opciones.map((opt, idx) => `
              <button type="button" class="btn text-start p-3 rounded-3 d-flex align-items-center justify-content-between challenge-option-btn ${l.completed && opt.es_correcta ? 'opt-correct' : ''}" data-index="${idx}" data-correct="${opt.es_correcta}">
                <div class="d-flex align-items-center gap-3">
                  <span class="challenge-option-letter">${String.fromCharCode(65 + idx)}</span>
                  <span class="challenge-option-text">${DOMPurify.sanitize(marked.parseInline(opt.texto ?? ''))}</span>
                </div>
                <i class="bi ${l.completed && opt.es_correcta ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'} option-check-icon"></i>
              </button>
            `).join('')}
          </div>

          <!-- Feedback interactivo -->
          <div id="challenge-feedback" class="p-3 rounded-3 ${l.completed ? 'alert alert-success d-flex align-items-center gap-2 mb-0' : 'd-none'}" style="font-size:.9rem;line-height:1.45;">
            ${l.completed ? `<i class="bi bi-check-circle-fill fs-5 text-success"></i> <div>¡Completado con éxito! Puedes repasar cuando desees.</div>` : ''}
          </div>
        </div>`;
    }

    // Render principal
    $('#leccion-card').innerHTML = `
      <!-- Cabecera de la Lección -->
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <span class="header-stat-pill">
            <i class="bi bi-clock text-primary"></i> ${l.estimated_minutes} min estimados
          </span>
          <span class="header-stat-pill">
            <i class="bi bi-lightning-charge-fill text-warning"></i> +${l.xp_reward} XP
          </span>
        </div>
        <span class="pill-edugt ${l.completed ? 'pill-done' : 'pill-now'}" id="pill-estado">
          ${l.completed ? '<i class="bi bi-check2-circle"></i> Completado' : '<i class="bi bi-play-fill"></i> En progreso'}
        </span>
      </div>

      <!-- Contenido Teórico -->
      <article class="lesson-markdown mb-4">
        ${htmlContent}
      </article>

      <!-- Bloque de Pseudocódigo -->
      ${pseudocodeHtml}

      <!-- Bloque de Reto / Acertijo -->
      ${retoHtml}

      <!-- Barra de Acción Final -->
      <div class="text-center mt-5 pt-4 border-top" style="border-color:var(--edugt-border)!important;">
        <button class="btn btn-edugt btn-lg px-5 shadow-sm" id="btn-completar">
          ${l.completed 
            ? '<i class="bi bi-arrow-repeat"></i> Ya completado · Registrar repaso' 
            : `<i class="bi bi-check-circle-fill"></i> ¡Entendido! Completar lección (+${l.xp_reward} XP)`}
        </button>
        <p class="mt-2 mb-0 text-muted" style="font-size:.85rem">
          <i class="bi bi-stopwatch me-1"></i> Tiempo de estudio en esta sesión: <strong id="tiempo-vivo">0 s</strong>
        </p>
      </div>`;

    // Interactividad del Reto
    if (l.pseudocode_json && l.pseudocode_json.reto) {
      const r = l.pseudocode_json.reto;
      const btnCompletar = $('#btn-completar');

      if (!l.completed) {
        btnCompletar.disabled = true;
        btnCompletar.classList.add('opacity-50');
        btnCompletar.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Responde el reto para desbloquear la lección';
      }

      document.querySelectorAll('.challenge-option-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          if (l.completed) return;

          const idx = parseInt(btn.dataset.index, 10);
          const opt = r.opciones[idx];
          const fb = $('#challenge-feedback');

          // Reset styles
          document.querySelectorAll('.challenge-option-btn').forEach(b => {
            b.classList.remove('opt-correct', 'opt-incorrect');
            const icon = b.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-circle option-check-icon text-muted';
          });

          if (opt.es_correcta) {
            btn.classList.add('opt-correct');
            const icon = btn.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-check-circle-fill option-check-icon text-success';

            fb.className = 'p-3 rounded-3 alert alert-success d-flex align-items-center gap-2 mb-0 animate-fade-in';
            fb.innerHTML = `<i class="bi bi-check-circle-fill fs-5 text-success"></i> <div>${DOMPurify.sanitize(marked.parseInline(opt.feedback ?? ''))}</div>`;
            fb.classList.remove('d-none');

            btnCompletar.disabled = false;
            btnCompletar.classList.remove('opacity-50');
            btnCompletar.classList.add('btn-pulse');
            btnCompletar.innerHTML = `<i class="bi bi-check-circle-fill"></i> ¡Entendido! Completar lección (+${l.xp_reward} XP) 🎉`;
          } else {
            btn.classList.add('opt-incorrect');
            const icon = btn.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-x-circle-fill option-check-icon text-danger';

            fb.className = 'p-3 rounded-3 alert alert-danger d-flex align-items-center gap-2 mb-0 animate-fade-in';
            fb.innerHTML = `<i class="bi bi-exclamation-circle-fill fs-5 text-danger"></i> <div>${DOMPurify.sanitize(marked.parseInline(opt.feedback ?? ''))}</div>`;
            fb.classList.remove('d-none');

            btnCompletar.disabled = true;
            btnCompletar.classList.add('opacity-50');
            btnCompletar.classList.remove('btn-pulse');
            btnCompletar.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Intenta de nuevo para desbloquear la lección';
          }
        });
      });
    }

    // Botón copiar código
    $('#btn-copy-code')?.addEventListener('click', (e) => {
      const codeEl = $('.code-window-body code');
      if (codeEl) {
        navigator.clipboard.writeText(codeEl.textContent);
        const btn = e.currentTarget;
        btn.innerHTML = '<i class="bi bi-check-lg text-success me-1"></i> ¡Copiado!';
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copiar';
        }, 2000);
      }
    });

    // Reloj visible
    const tEl = $('#tiempo-vivo');
    setInterval(() => {
      if (tEl) {
        const mins = Math.floor(segundosEnPagina / 60);
        const secs = segundosEnPagina % 60;
        tEl.textContent = mins > 0 ? `${mins}m ${secs < 10 ? '0' : ''}${secs}s` : `${secs} s`;
      }
    }, 1000);

    $('#btn-completar').addEventListener('click', completar);
  } catch (err) {
    if (err.status === 403) {
      const needsPretest = !Auth.user?.learning_track;
      $('#leccion-card').innerHTML = `
        <div class="text-center py-5">
          <div class="stat-icon mx-auto mb-3" style="width:64px;height:64px;background:#F1EFFB;color:var(--edugt-muted);font-size:2rem;">
            <i class="bi bi-lock-fill"></i>
          </div>
          <h2 class="h5 fw-bold mt-2">${needsPretest ? 'Completa primero tu Diagnóstico Inicial' : 'Lección bloqueada'}</h2>
          <p class="text-muted mb-4">${needsPretest
            ? 'Para desbloquear el Módulo 1 debes realizar primero la Prueba Diagnóstica Inicial (Pre-test).'
            : 'Completa la lección anterior y su evaluación para desbloquear este contenido.'}</p>
          <a href="${needsPretest ? 'diagnostico.html?kind=pre' : 'estudiante.html'}" class="btn btn-edugt">
            ${needsPretest ? '<i class="bi bi-compass-fill me-1"></i> Realizar Pre-test Diagnóstico' : 'Volver a mi ruta'}
          </a>
        </div>`;
      return;
    }
    showAlert(err.message || 'Error al cargar la lección');
  }
}

/* ---------------- Acción Completar Lección ---------------- */
async function completar() {
  const btn = $('#btn-completar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Guardando progreso...';

  try {
    const { data } = await api(`/lessons/${lessonId}/complete`, {
      method: 'POST',
      body: { time_spent_seconds: segundosEnPagina },
    });

    clearInterval(clock);

    $('#leccion-card').innerHTML = `
      <div class="text-center py-5 animate-fade-in">
        <div class="stat-icon mx-auto mb-3" style="width:76px;height:76px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:2.2rem;border-radius:24px;">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <h2 class="h4 fw-bold mb-2">
          ${data.xp_awarded ? `¡Excelente trabajo! +${data.xp_amount} XP` : '¡Repaso registrado con éxito!'}
        </h2>
        ${data.new_badges && data.new_badges.length > 0 ? `
          <div class="mb-3">
            <span class="badge px-3 py-2 rounded-pill shadow-xs" style="font-size:.95rem;background:var(--edugt-primary-soft);color:var(--edugt-primary);border:1px solid var(--edugt-primary);">
              <i class="bi bi-award-fill me-1"></i> ¡Nueva insignia conseguida: ${esc(data.new_badges[0].title)}!
            </span>
          </div>` : ''}
        <p class="text-muted mb-4" style="max-width:480px;margin:0 auto;font-size:.95rem;">
          ${data.xp_awarded 
            ? 'Has completado esta lección. Tu experiencia ha sido sumada y la siguiente lección en tu ruta de aprendizaje ya está disponible.' 
            : 'Tu tiempo de estudio ha sido añadido al registro de tu progreso.'}
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="estudiante.html" class="btn btn-edugt btn-lg px-4 shadow-sm">
            <i class="bi bi-map"></i> Volver a mi ruta
          </a>
        </div>
      </div>`;
  } catch (err) {
    showAlert(err.message || 'Error al guardar el progreso');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Completar lección';
  }
}

boot();
