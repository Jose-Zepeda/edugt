/* ==========================================================================
   EduGT — Quiz Card-Flow con feedback inmediato (RF-11..RF-16)
   RB-10: el navegador NUNCA califica — cada respuesta va al servidor y el
   servidor devuelve {is_correct, feedback}. El submit también es server-side.
   ========================================================================== */
'use strict';

Auth.load();
if (!Auth.token) window.location.href = 'index.html';

const $ = (sel) => document.querySelector(sel);
const quizId = new URLSearchParams(window.location.search).get('id');
if (!quizId) window.location.href = 'estudiante.html';

let attemptId = null;
let attemptNumber = null;
let questions = [];
let cursor = 0;
let lastResult = null; // {score, passed, ...}

function showAlert(msg) {
  const el = $('#alert');
  el.className = 'alert alert-danger';
  el.textContent = msg;
}

/* ---------------- Flujo ---------------- */
async function start() {
  // Descarga del quiz (sin is_correct — RB-10)
  const { data: quizData } = await api(`/quizzes/${quizId}`);
  questions = quizData.data.questions;

  // Abre el intento (ordinal en servidor)
  const { data: att } = await api(`/quizzes/${quizId}/attempts`, { method: 'POST' });
  attemptId = att.attempt_id;
  attemptNumber = att.attempt_number;

  renderQuestion();
}

function updateProgress() {
  $('#progress-label').textContent = `Pregunta ${Math.min(cursor + 1, questions.length)} de ${questions.length}`;
  $('#progress-bar').style.width = `${(cursor / questions.length) * 100}%`;
}

function renderQuestion() {
  const q = questions[cursor];
  if (!q) return renderFinish();

  $('#quiz-card').innerHTML = `
    ${attemptNumber > 1 ? `<small style="color:var(--edugt-amber)"><i class="bi bi-arrow-repeat"></i> Intento ${attemptNumber} — si apruebas, el XP se reduce un 20 % por intento adicional</small>` : ''}
    <h2 class="h5 fw-bold mt-2 mb-4">${esc(q.statement)}</h2>

    <div class="d-grid gap-2" id="options">
      ${q.options.map((o) => `
        <button type="button" class="btn btn-option text-start" data-oid="${o.id}">
          <span class="option-letter me-2">•</span>${esc(o.option_text)}
        </button>`).join('')}
    </div>

    <div id="feedback" class="mt-4 d-none"></div>`;

  updateProgress();

  document.querySelectorAll('.btn-option').forEach((b) => {
    b.addEventListener('click', () => answer(Number(b.dataset.oid), b));
  });
}

/* ---------------- Respuesta + feedback inmediato (RF-13) ---------------- */
async function answer(optionId, btn) {
  const q = questions[cursor];
  document.querySelectorAll('.btn-option').forEach((b) => (b.disabled = true));
  btn.style.borderColor = 'var(--edugt-primary)';

  try {
    const { data } = await api(`/quizzes/${quizId}/attempts/${attemptId}/answers`, {
      method: 'POST',
      body: { question_id: q.id, option_id: optionId },
    });

    // Feedback según el servidor
    const fb = $('#feedback');
    fb.className = `mt-4 border rounded-4 p-3 ${data.is_correct ? 'border-success' : 'border-danger'}`;
    fb.style.background = data.is_correct ? 'var(--edugt-green-soft)' : '#FEE2E2';
    fb.innerHTML = `
      <div class="fw-bold mb-1" style="color:${data.is_correct ? 'var(--edugt-green)' : '#B91C1C'}">
        <i class="bi ${data.is_correct ? 'bi-check-circle' : 'bi-x-circle'}"></i>
        ${data.is_correct ? '¡Correcto!' : 'Incorrecto'}
      </div>
      <div style="font-size:.9rem;">${esc(data.feedback ?? '')}</div>`;

    const nextBtn = document.createElement('button');
    nextBtn.className = 'btn btn-edugt mt-3 w-100';
    nextBtn.innerHTML = cursor + 1 < questions.length ? 'Siguiente pregunta' : 'Finalizar evaluación';
    nextBtn.addEventListener('click', () => { cursor++; renderQuestion(); });
    fb.appendChild(nextBtn);
    fb.classList.remove('d-none');
  } catch (err) {
    showAlert(err.message);
    document.querySelectorAll('.btn-option').forEach((b) => (b.disabled = false));
    btn.style.borderColor = 'var(--edugt-border)';
  }
}

/* ---------------- Final (submit server-side = score real) ---------------- */
function renderFinish() {
  $('#progress-bar').style.width = '100%';
  $('#progress-label').textContent = 'Listo para entregar';

  const shown = questions.reduce((acc, q) => (q?.answered ? acc + 1 : acc), 0);

  $('#quiz-card').innerHTML = `
    <div class="text-center py-4">
      <i class="bi bi-clipboard-check" style="font-size:2.5rem;color:var(--edugt-primary)"></i>
      <h2 class="h5 fw-bold mt-2">Entregar evaluación</h2>
      <p style="color:var(--edugt-muted)">Tu calificación se calcula en el servidor con las respuestas guardadas.</p>
      <button class="btn btn-edugt btn-lg mt-2" id="btn-submit">Entregar</button>
    </div>`;
  $('#btn-submit').addEventListener('click', submit);
}

async function submit() {
  const btn = $('#btn-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass"></i> Calificando...';

  try {
    const { data } = await api(`/quizzes/${quizId}/attempts/${attemptId}/submit`, { method: 'POST' });
    lastResult = data;

    const approved = data.passed;
    $('#quiz-card').innerHTML = `
      <div class="text-center py-4">
        <div class="stat-icon mx-auto mb-3" style="width:76px;height:76px;border-radius:22px;background:${approved ? 'var(--edugt-green-soft)' : '#FEE2E2'};color:${approved ? 'var(--edugt-green)' : '#B91C1C'}">
          <i class="bi ${approved ? 'bi-trophy' : 'bi-arrow-repeat'}" style="font-size:2rem"></i>
        </div>
        <h2 class="h5 fw-bold">${approved ? '¡Nivel desbloqueado!' : 'Muy cerca — inténtalo de nuevo'}</h2>
        <div class="display-4 fw-bold my-2" style="color:${approved ? 'var(--edugt-green)' : 'var(--edugt-amber)'}">${data.score}</div>
        <small style="color:var(--edugt-muted)">Intento ${data.attempt_number}</small>

        ${approved && data.xp_awarded
          ? `<div class="mt-3 badge rounded-pill p-3" style="background:var(--edugt-amber-soft);color:var(--edugt-amber);font-size:1rem">
               <i class="bi bi-lightning-charge-fill"></i> +${data.xp_amount} XP
             </div>`
          : ''}

        <p class="mt-3 mb-0" style="color:var(--edugt-muted)">
          ${approved
            ? 'Puedes continuar con la siguiente lección de tu ruta.'
            : 'Necesitas 70 o más para desbloquear el siguiente nivel. Revisa la teoría y vuelve a intentarlo — los reintentos son ilimitados (el XP otorgado baja 20 % por cada intento adicional).'}
        </p>

        <a href="estudiante.html" class="btn btn-edugt btn-lg mt-3">Volver a mi ruta</a>
      </div>`;
  } catch (err) {
    showAlert(err.message);
    btn.disabled = false;
    btn.innerHTML = 'Entregar';
  }
}

start().catch((err) => {
  if (err.status === 401) { Auth.clear(); window.location.href = 'index.html'; return; }
  showAlert(err.message);
});
