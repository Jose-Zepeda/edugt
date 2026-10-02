/* ==========================================================================
   EduGT — Vista de lección (RF-9, RF-10)
   Markdown sanitizado con DOMPurify (tesis §Seguridad: sanitizar Markdown).
   El cronómetro mide tiempo real en página; el backend otorga el XP (RB-10).
   ========================================================================== */
'use strict';

Auth.load();
if (!Auth.token) window.location.href = 'index.html';

const $ = (sel) => document.querySelector(sel);
const lessonId = new URLSearchParams(window.location.search).get('id');

// Cronómetro de tiempo invertido (se manda al completar)
let segundosEnPagina = 0;
const clock = setInterval(() => { segundosEnPagina++; }, 1000);

// Aviso al salir sin completar: guardar el tiempo igualmente (RF-10: tiempo invertido)
window.addEventListener('beforeunload', () => {
  if (segundosEnPagina > 5 && lessonId) {
    // sendBeacon sobrevive al cierre de página
    navigator.sendBeacon?.(
      `${API_BASE}/lessons/${lessonId}/complete`,
      new Blob([JSON.stringify({ time_spent_seconds: segundosEnPagina })], { type: 'application/json' }),
    );
  }
});

function showAlert(msg) {
  const el = $('#alert');
  el.className = 'alert alert-danger';
  el.textContent = msg;
}

/* ---------------- Render ---------------- */
async function boot() {
  if (!lessonId) {
    $('#leccion-card').innerHTML = '<p class="text-center py-4" style="color:var(--edugt-muted)">Lección no encontrada.</p>';
    return;
  }

  try {
    const { data } = await api(`/lessons/${lessonId}`);
    const l = data.data;

    // Sanity: DOMPurify aplicado al HTML de marked (XSS)
    const html = DOMPurify.sanitize(marked.parse(l.content_markdown ?? ''));

    const pseudocode = l.pseudocode_json
      ? `
      <section class="mt-4">
        <h2 class="h6 fw-bold mb-2"><i class="bi bi-terminal" style="color:var(--edugt-primary)"></i> Pseudocódigo</h2>
        <pre class="border rounded-4 p-3" style="background:#1E1B2E;color:#E4E1F5;font-family:ui-monospace,Consolas,monospace;font-size:.9rem;overflow-x:auto;">${esc(JSON.stringify(l.pseudocode_json.ejemplo ?? l.pseudocode_json, null, 2).replace(/"/g, ''))}</pre>
      </section>`
      : '';

    $('#leccion-card').innerHTML = `
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <small style="color:var(--edugt-muted)"><i class="bi bi-clock"></i> ${l.estimated_minutes} min estimados · <i class="bi bi-lightning"></i> ${l.xp_reward} XP al completar</small>
        <span class="pill-edugt ${l.completed ? 'pill-done' : 'pill-now'}" id="pill-estado">
          ${l.completed ? '<i class="bi bi-check-lg"></i> Completada' : '<i class="bi bi-play-fill"></i> En curso'}
        </span>
      </div>

      <h1 class="h4 fw-bold mb-4">${esc(l.title)}</h1>

      <article class="leccion-markdown">${html}</article>
      ${pseudocode}

      <div class="text-center mt-5">
        <button class="btn btn-edugt btn-lg" id="btn-completar">
          ${l.completed ? 'Registrar tiempo adicional' : '<i class="bi bi-check-circle"></i> Completar lección'}
        </button>
        <p class="mt-2 mb-0" style="color:var(--edugt-muted);font-size:.85rem">
          Tiempo en esta visita: <strong id="tiempo-vivo">0 s</strong>
        </p>
      </div>`;

    // Reloj visible
    const tEl = $('#tiempo-vivo');
    setInterval(() => { tEl.textContent = `${segundosEnPagina} s`; }, 1000);

    $('#btn-completar').addEventListener('click', completar);
  } catch (err) {
    if (err.status === 403) {
      $('#leccion-card').innerHTML = `
        <div class="text-center py-5">
          <i class="bi bi-lock" style="font-size:2.5rem;color:var(--edugt-muted)"></i>
          <h2 class="h5 fw-bold mt-3">Lección bloqueada</h2>
          <p style="color:var(--edugt-muted)">Completa las lecciones anteriores y su evaluación para desbloquear esta.</p>
          <a href="estudiante.html" class="btn btn-edugt">Volver a mi ruta</a>
        </div>`;
      return;
    }
    showAlert(err.message);
  }
}

async function completar() {
  const btn = $('#btn-completar');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass"></i> Guardando...';

  try {
    const { data } = await api(`/lessons/${lessonId}/complete`, {
      method: 'POST',
      body: { time_spent_seconds: segundosEnPagina },
    });

    clearInterval(clock);

    $('#leccion-card').innerHTML = `
      <div class="text-center py-5">
        <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;background:var(--edugt-green-soft);color:var(--edugt-green)">
          <i class="bi bi-check-lg" style="font-size:2rem"></i>
        </div>
        <h2 class="h5 fw-bold">${data.xp_awarded ? `¡Lección completada! +${data.xp_amount} XP` : 'Tiempo registrado'}</h2>
        <p style="color:var(--edugt-muted)">
          ${data.xp_awarded ? 'Sigue así — la siguiente lección ya está desbloqueada.' : 'Ya habías completado esta lección; el tiempo adicional sumó a tu registro.'}
        </p>
        <a href="estudiante.html" class="btn btn-edugt btn-lg mt-2">Volver a mi ruta</a>
      </div>`;
  } catch (err) {
    showAlert(err.message);
    btn.disabled = false;
    btn.innerHTML = 'Completar lección';
  }
}

boot();
