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

/* ---------------- Estado visible ---------------- */
function updateProgress() {
  $('#progress-label').textContent = `Pregunta ${Math.min(cursor + 1, questions.length)} de ${questions.length}`;
  $('#progress-bar').style.width = `${questions.length ? (cursor / questions.length) * 100 : 0}%`;
}

function renderQuestion() {
  const q = questions[cursor];
  if (!q) return renderSubmit();

  $('#quiz-card').innerHTML = `
    <small class="fw-bold text-uppercase" style="color:var(--edugt-primary);letter-spacing:.06em">${escDimension(q.dimension)}</small>
    <h2 class="h5 fw-bold mt-2 mb-4">${esc(q.statement)}</h2>
    <div class="d-grid gap-2" id="options">
      ${q.options.map((o, i) => `
        <button type="button" class="btn btn-option text-start" data-index="${i}">
          <span class="option-letter me-2">${String.fromCharCode(65 + i)}</span>${esc(o)}
        </button>`).join('')}
    </div>`;

  updateProgress();

  document.querySelectorAll('.btn-option').forEach((btn) => {
    btn.addEventListener('click', () => selectOption(Number(btn.dataset.index)));
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
async function selectOption(index) {
  const q = questions[cursor];
  document.querySelectorAll('.btn-option').forEach((b) => (b.disabled = true));

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
    showAlert(err.message);
    document.querySelectorAll('.btn-option').forEach((b) => (b.disabled = false));
  }
}

/* ---------------- Pantalla final ---------------- */
function renderSubmit() {
  $('#progress-label').textContent = '¡Listo!';
  $('#progress-bar').style.width = '100%';

  $('#quiz-card').innerHTML = `
    <div class="text-center py-3">
      <div class="stat-icon mx-auto mb-3" style="width:64px;height:64px;background:var(--edugt-green-soft);color:var(--edugt-green)">
        <i class="bi bi-check-lg" style="font-size:1.8rem"></i>
      </div>
      <h2 class="h5 fw-bold">Respondiste todo</h2>
      <p style="color:var(--edugt-muted)">Entrega tus respuestas para ver tu resultado y tu ruta de aprendizaje.</p>
      <button class="btn btn-edugt btn-lg mt-2" id="btn-submit">Ver mi resultado</button>
    </div>`;
  $('#btn-submit').addEventListener('click', submit);
}

async function submit() {
  const btn = $('#btn-submit');
  btn.disabled = true;
  btn.textContent = 'Calculando...';

  try {
    const { data } = await api(`/diagnostic/tests/${kind}/submit`, { method: 'POST' });

    $('#quiz-card').innerHTML = `
      <div class="text-center py-3">
        <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;background:var(--edugt-primary-soft);color:var(--edugt-primary)">
          <i class="bi bi-compass" style="font-size:2rem"></i>
        </div>
        <h2 class="h5 fw-bold">Tu resultado</h2>
        <div class="display-4 fw-bold my-3" style="color:var(--edugt-primary)">${data.score_total}%</div>
        <p style="color:var(--edugt-muted)">Tu ruta asignada: <strong>${escTrack(data.learning_track)}</strong></p>
        <a href="estudiante.html" class="btn btn-edugt btn-lg mt-2">Ir a mi ruta</a>
      </div>`;

    // Refresca el usuario en sesión con el learning_track nuevo
    if (data.learning_track) {
      Auth.user.learning_track = data.learning_track;
      sessionStorage.setItem('edugt_user', JSON.stringify(Auth.user));
    }
  } catch (err) {
    showAlert(err.message);
    btn.disabled = false;
    btn.textContent = 'Ver mi resultado';
  }
}

function escTrack(t) {
  return { beginner: 'Básica', intermediate: 'Intermedia', advanced: 'Avanzada' }[t] ?? '—';
}

function showAlert(message) {
  const el = $('#alert');
  el.className = 'alert alert-danger';
  el.textContent = message;
}

/* ---------------- Boot ---------------- */
(async function boot() {
  try {
    const { data } = await api(`/diagnostic/tests/${kind}`);
    questions = data.data.questions;
    cursor = data.data.answered ?? 0; // reanudar donde iba (RF-28)
    if (cursor >= questions.length) return renderSubmit();
    renderQuestion();
  } catch (err) {
    if (err.code === 'DIAGNOSTIC_ALREADY_COMPLETED') {
      $('#quiz-card').innerHTML = `
        <div class="text-center py-4">
          <h2 class="h5 fw-bold">Ya completaste esta prueba</h2>
          <p style="color:var(--edugt-muted)">El postest se habilita al final del proceso.</p>
          <a href="estudiante.html" class="btn btn-edugt">Volver a mi panel</a>
        </div>`;
      return;
    }
    showAlert(err.message);
  }
})();
