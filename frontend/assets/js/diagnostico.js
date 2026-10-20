/* ==========================================================================
   EduGT — Prueba diagnóstica Card-Flow (RF-6, RF-28; RB-11: sin recompensas)
   Una pregunta por pantalla, respuesta por respuesta (reanudable server-side)
   ========================================================================== */
'use strict';

Auth.load();
if (!Auth.token) window.location.href = 'index.html';

const $ = (sel) => document.querySelector(sel);

const kind = new URLSearchParams(window.location.search).get('kind') === 'post' ? 'post' : 'pre';

let questions = [];
let cursor = 0; // índice de la pregunta actual (solo navegación local; la verdad vive en el servidor)

/* ---------------- Configuración Visual del Encabezado ---------------- */
function setupHeader() {
  const isPost = kind === 'post';
  document.title = isPost ? 'EduGT — Evaluación Final (Post-test)' : 'EduGT — Prueba Inicial (Pre-test)';
  
  const titleEl = $('#diag-title-text');
  if (titleEl) {
    titleEl.textContent = isPost ? 'Evaluación Final (Post-test)' : 'Diagnóstico Inicial (Pre-test)';
  }
}

/* ---------------- Estado visible ---------------- */
function updateProgress() {
  const total = questions.length;
  const current = Math.min(cursor + 1, total);
  const label = $('#progress-label');
  const bar = $('#progress-bar');
  
  if (label) label.textContent = total > 0 ? `Pregunta ${current} de ${total}` : 'Cargando preguntas...';
  if (bar) bar.style.width = `${total > 0 ? (cursor / total) * 100 : 0}%`;
}

function renderQuestion() {
  const q = questions[cursor];
  if (!q) return renderSubmit();

  const isPost = kind === 'post';
  const tagLabel = isPost ? 'Evaluación Final' : 'Diagnóstico Inicial';

  $('#quiz-card').innerHTML = `
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill">
        <i class="bi bi-diagram-3-fill me-1"></i> ${escDimension(q.dimension)}
      </span>
      <span class="text-muted small">${tagLabel}</span>
    </div>

    <h2 class="h5 fw-bold mb-4" style="color:var(--edugt-text);line-height:1.45;">${esc(q.statement)}</h2>

    <div class="d-flex flex-column gap-2" id="options">
      ${q.options.map((o, i) => `
        <button type="button" class="btn btn-option text-start d-flex align-items-center justify-content-between" data-index="${i}">
          <div class="d-flex align-items-center gap-3">
            <span class="option-letter">${String.fromCharCode(65 + i)}</span>
            <span class="option-text">${esc(o)}</span>
          </div>
          <i class="bi bi-arrow-right-short text-muted option-arrow" style="font-size:1.3rem;"></i>
        </button>`).join('')}
    </div>`;

  updateProgress();

  document.querySelectorAll('.btn-option').forEach((btn) => {
    btn.addEventListener('click', () => selectOption(Number(btn.dataset.index), btn));
  });
}

function escDimension(d) {
  const names = {
    abstraction: 'Abstracción', decomposition: 'Descomposición', pattern_recognition: 'Patrones',
    algorithms: 'Algoritmos', variables: 'Variables', conditionals: 'Condicionales', loops: 'Ciclos',
  };
  return names[d] ?? d;
}

/* ---------------- Responder (1 POST por respuesta → reanudable) ---------------- */
async function selectOption(index, btn) {
  const q = questions[cursor];
  document.querySelectorAll('.btn-option').forEach((b) => (b.disabled = true));
  if (btn) btn.classList.add('selected');

  try {
    await api(`/diagnostic/tests/${kind}/answers`, {
      method: 'POST',
      body: { question_id: q.id, selected_index: index },
    });

    cursor++;
    renderQuestion();
  } catch (err) {
    if (err.code === 'DIAGNOSTIC_ALREADY_COMPLETED') {
      window.location.href = 'estudiante.html';
      return;
    }
    showAlert(err.message || 'Error al registrar tu respuesta');
    document.querySelectorAll('.btn-option').forEach((b) => {
      b.disabled = false;
      b.classList.remove('selected');
    });
  }
}

/* ---------------- Pantalla final ---------------- */
function renderSubmit() {
  const isPost = kind === 'post';
  $('#progress-label').textContent = '¡Prueba completada!';
  $('#progress-bar').style.width = '100%';

  $('#quiz-card').innerHTML = `
    <div class="text-center py-4 animate-fade-in">
      <div class="stat-icon mx-auto mb-3" style="width:68px;height:68px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.8rem;border-radius:20px;">
        <i class="bi bi-check2-all"></i>
      </div>
      <h2 class="h4 fw-bold mb-2" style="color:var(--edugt-text);">Has respondido todas las preguntas</h2>
      <p style="color:var(--edugt-muted);max-width:440px;margin:0 auto 1.5rem;font-size:.92rem;">
        ${isPost 
          ? 'Entrega tus respuestas para calcular tu desempeño final y certificar tu avance en el programa.' 
          : 'Entrega tus respuestas para calcular y registrar tu nivel diagnóstico inicial.'}
      </p>
      <button class="btn btn-edugt btn-lg px-5 shadow-sm" id="btn-submit">
        <i class="bi bi-send-check me-1"></i> Ver mi resultado
      </button>
    </div>`;
  $('#btn-submit').addEventListener('click', submit);
}

async function submit() {
  const btn = $('#btn-submit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Calculando en servidor...';

  try {
    const { data } = await api(`/diagnostic/tests/${kind}/submit`, { method: 'POST' });
    const isPost = kind === 'post';

    $('#quiz-card').innerHTML = `
      <div class="text-center py-4 animate-fade-in">
        <div class="stat-icon mx-auto mb-3" style="width:76px;height:76px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:2.2rem;border-radius:24px;">
          <i class="bi ${isPost ? 'bi-mortarboard-fill text-success' : 'bi-compass'}"></i>
        </div>
        <h2 class="h4 fw-bold mb-1" style="color:var(--edugt-text);">${isPost ? '¡Evaluación Final (Post-test) Completada!' : '¡Resultado del Diagnóstico!'}</h2>
        <div class="display-3 fw-bold my-3" style="color:var(--edugt-primary);letter-spacing:-0.03em;">${data.score_total}%</div>
        
        ${isPost && data.pre_score !== null && data.pre_score !== undefined ? `
          <div class="d-inline-flex align-items-center gap-2 badge px-3 py-2 rounded-pill mb-3" style="font-size:0.92rem;background:var(--edugt-green-soft);color:var(--edugt-green);border:1px solid var(--edugt-green);">
            <i class="bi bi-graph-up-arrow"></i> Pre-test: <strong>${data.pre_score}%</strong> → Post-test: <strong>${data.score_total}%</strong> (${data.gain >= 0 ? '+' : ''}${data.gain} pts)
          </div>` : ''}

        ${data.learning_track ? `
          <div class="d-inline-flex align-items-center gap-2 badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill mb-3" style="font-size:0.95rem;">
            <i class="bi bi-bar-chart-fill"></i> Nivel diagnóstico: <strong>${escTrack(data.learning_track)}</strong>
          </div>` : ''}

        <p style="color:var(--edugt-muted);max-width:460px;margin:0 auto 1.5rem;font-size:.92rem;">
          ${isPost 
            ? '¡Felicidades por culminar el programa formativo de EduGT! Tu Certificado Oficial en PDF ya está disponible.' 
            : 'Tu resultado inicial ha sido registrado para medir tu progreso a lo largo de los módulos.'}
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          ${isPost ? `
            <button type="button" class="btn btn-edugt btn-lg px-4 shadow-sm" onclick="window.downloadCertFromDiag(this)">
              <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar Certificado (PDF)
            </button>
          ` : ''}
          <a href="estudiante.html" class="btn ${isPost ? 'btn-google' : 'btn-edugt'} btn-lg px-4 shadow-sm">
            <i class="bi bi-arrow-right-circle me-1"></i> ${isPost ? 'Volver a mi panel' : 'Ir a mis módulos'}
          </a>
        </div>
      </div>`;

    // Refresca el usuario en sesión (localStorage + sessionStorage) con el learning_track nuevo
    if (data.learning_track) {
      Auth.user.learning_track = data.learning_track;
      Auth.save(Auth.token, Auth.user);
    }
  } catch (err) {
    showAlert(err.message || 'Error al procesar la entrega');
    btn.disabled = false;
    btn.textContent = 'Ver mi resultado';
  }
}

window.downloadCertFromDiag = async function (btnEl) {
  const orig = btnEl ? btnEl.innerHTML : '';
  if (btnEl) {
    btnEl.disabled = true;
    btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Generando diploma...';
  }
  try {
    const res = await fetch(`${API_BASE}/me/certificate`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${Auth.token}`,
        Accept: 'application/pdf',
      },
    });
    if (!res.ok) throw new Error('Error al generar el certificado PDF');
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `certificado-edugt-${(Auth.user?.name || 'estudiante').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (err) {
    showAlert(err.message || 'Error al descargar el certificado');
  } finally {
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerHTML = orig;
    }
  }
};

function escTrack(t) {
  return { beginner: 'Básica', intermediate: 'Intermedia', advanced: 'Avanzada' }[t] ?? '—';
}

function showAlert(message) {
  const el = $('#alert');
  if (!el) return;
  el.className = 'alert alert-danger';
  el.textContent = message;
  el.classList.remove('d-none');
}

/* ---------------- Boot ---------------- */
(async function boot() {
  setupHeader();
  try {
    const { data } = await api(`/diagnostic/tests/${kind}`);
    questions = data.data.questions;
    cursor = data.data.answered ?? 0; // reanudar donde iba (RF-28)
    if (cursor >= questions.length) return renderSubmit();
    renderQuestion();
  } catch (err) {
    if (err.code === 'DIAGNOSTIC_ALREADY_COMPLETED') {
      const isPost = kind === 'post';
      $('#quiz-card').innerHTML = `
        <div class="text-center py-4 animate-fade-in">
          <div class="stat-icon mx-auto mb-3" style="width:68px;height:68px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:1.8rem;border-radius:20px;">
            <i class="bi bi-check-circle-fill"></i>
          </div>
          <h2 class="h5 fw-bold mb-2" style="color:var(--edugt-text);">Ya completaste esta prueba</h2>
          <p style="color:var(--edugt-muted);max-width:400px;margin:0 auto 1.5rem;font-size:.92rem;">
            ${isPost ? 'Ya has enviado tu evaluación final.' : 'Ya has realizado el diagnóstico inicial y tu ruta de aprendizaje está activa.'}
          </p>
          <a href="estudiante.html" class="btn btn-edugt px-4 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver a mi panel
          </a>
        </div>`;
      return;
    }
    showAlert(err.message || 'No fue posible cargar las preguntas');
  }
})();
