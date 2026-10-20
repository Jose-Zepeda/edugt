/* ==========================================================================
   EduGT — Quiz Card-Flow con feedback inmediato y Markdown (RF-11..RF-16)
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
let quizTitle = 'Evaluación';
let questions = [];
let cursor = 0;
let lastResult = null;

function showAlert(msg) {
  const el = $('#alert');
  if (el) {
    el.className = 'alert alert-danger';
    el.textContent = msg;
    el.classList.remove('d-none');
  }
}

/* ---------------- Flujo Inicial ---------------- */
async function start() {
  try {
    // Descarga del quiz (sin is_correct — RB-10)
    const { data: quizData } = await api(`/quizzes/${quizId}`);
    questions = quizData.data.questions || [];
    quizTitle = quizData.data.title || 'Evaluación de Módulo';

    if ($('#quiz-badge-title')) {
      $('#quiz-badge-title').innerHTML = `<i class="bi bi-award-fill text-warning me-1"></i> ${esc(quizTitle)}`;
    }

    // Abre el intento (ordinal en servidor)
    const { data: att } = await api(`/quizzes/${quizId}/attempts`, { method: 'POST' });
    attemptId = att.attempt_id;
    attemptNumber = att.attempt_number;

    renderQuestion();
  } catch (err) {
    if (err.status === 401) {
      Auth.clear();
      window.location.href = 'index.html';
      return;
    }
    showAlert(err.message || 'Error al iniciar la evaluación');
  }
}

function updateProgress() {
  const current = Math.min(cursor + 1, questions.length);
  $('#progress-label').textContent = `Pregunta ${current} de ${questions.length}`;
  $('#progress-bar').style.width = `${((current - 1) / questions.length) * 100}%`;
}

/* ---------------- Render de Pregunta ---------------- */
function renderQuestion() {
  const q = questions[cursor];
  if (!q) return renderFinish();

  updateProgress();

  // Formatear enunciado con Markdown y sanitización
  const statementHtml = DOMPurify.sanitize(marked.parse(q.statement ?? ''));

  $('#quiz-card').innerHTML = `
    ${attemptNumber > 1 ? `
      <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:.84rem;">
        <i class="bi bi-arrow-repeat text-warning fs-6"></i>
        <div><strong>Intento ${attemptNumber}:</strong> Si apruebas, la recompensa de XP se ajusta por reintento.</div>
      </div>` : ''}

    <div class="d-flex align-items-center justify-content-between mb-3">
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill">
        <i class="bi bi-question-circle me-1"></i> Pregunta ${cursor + 1}
      </span>
      <span class="text-muted" style="font-size:.82rem;">Selecciona una opción</span>
    </div>

    <!-- Enunciado de la Pregunta con Markdown/Código -->
    <div class="lesson-markdown mb-4" style="font-size:1.02rem;">
      ${statementHtml}
    </div>

    <!-- Opciones de Respuesta -->
    <div class="d-flex flex-column gap-2 mb-3" id="options">
      ${q.options.map((o, idx) => `
        <button type="button" class="btn text-start p-3 rounded-3 d-flex align-items-center justify-content-between challenge-option-btn" data-oid="${o.id}">
          <div class="d-flex align-items-center gap-3">
            <span class="challenge-option-letter">
              ${String.fromCharCode(65 + idx)}
            </span>
            <span class="challenge-option-text">${DOMPurify.sanitize(marked.parseInline(o.option_text ?? ''))}</span>
          </div>
          <i class="bi bi-circle option-check-icon text-muted"></i>
        </button>`).join('')}
    </div>

    <!-- Feedback inmediato -->
    <div id="feedback" class="mt-4 d-none"></div>`;

  document.querySelectorAll('.challenge-option-btn').forEach((b) => {
    b.addEventListener('click', () => answer(Number(b.dataset.oid), b));
  });
}

/* ---------------- Respuesta + feedback inmediato (RF-13) ---------------- */
async function answer(optionId, btn) {
  const q = questions[cursor];
  document.querySelectorAll('.challenge-option-btn').forEach((b) => (b.disabled = true));

  try {
    const { data } = await api(`/quizzes/${quizId}/attempts/${attemptId}/answers`, {
      method: 'POST',
      body: { question_id: q.id, option_id: optionId },
    });

    const isCorrect = data.is_correct;

    // Estilo del botón seleccionado mediante clases adaptables
    btn.classList.add(isCorrect ? 'opt-correct' : 'opt-incorrect');
    const icon = btn.querySelector('.option-check-icon');
    if (icon) {
      icon.className = `bi ${isCorrect ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} option-check-icon`;
    }

    // Feedback según el servidor
    const fb = $('#feedback');
    fb.className = `mt-3 p-3 rounded-3 alert ${isCorrect ? 'alert-success' : 'alert-danger'} animate-fade-in`;
    fb.innerHTML = `
      <div class="d-flex align-items-start gap-2 mb-2">
        <i class="bi ${isCorrect ? 'bi-check-circle-fill fs-5 text-success' : 'bi-x-circle-fill fs-5 text-danger'}"></i>
        <div>
          <div class="fw-bold mb-1" style="color:${isCorrect ? 'var(--edugt-green)' : '#EF4444'}">
            ${isCorrect ? '¡Respuesta correcta!' : 'Respuesta incorrecta'}
          </div>
          <div style="font-size:.9rem;line-height:1.45;">${DOMPurify.sanitize(marked.parseInline(data.feedback ?? ''))}</div>
        </div>
      </div>
      <button class="btn btn-edugt w-100 mt-2 shadow-sm" id="btn-next-question">
        ${cursor + 1 < questions.length ? '<i class="bi bi-arrow-right"></i> Siguiente pregunta' : '<i class="bi bi-check2-all"></i> Finalizar y ver resultados'}
      </button>`;

    $('#btn-next-question')?.addEventListener('click', () => {
      cursor++;
      renderQuestion();
    });

    fb.classList.remove('d-none');
  } catch (err) {
    showAlert(err.message || 'Error al registrar la respuesta');
    document.querySelectorAll('.challenge-option-btn').forEach((b) => (b.disabled = false));
  }
}

/* ---------------- Final (submit server-side = score real) ---------------- */
function renderFinish() {
  $('#progress-bar').style.width = '100%';
  $('#progress-label').textContent = 'Todas las preguntas contestadas';

  $('#quiz-card').innerHTML = `
    <div class="text-center py-5 animate-fade-in">
      <div class="stat-icon mx-auto mb-3" style="width:72px;height:72px;border-radius:20px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:2rem;">
        <i class="bi bi-clipboard-check-fill"></i>
      </div>
      <h2 class="h4 fw-bold mb-2">¡Has completado todas las preguntas!</h2>
      <p class="text-muted mb-4" style="max-width:440px;margin:0 auto;font-size:.92rem;">
        Presiona el botón de abajo para que el servidor procese tu calificación final y registre tus recompensas.
      </p>
      <button class="btn btn-edugt btn-lg px-5 shadow-sm" id="btn-submit">
        <i class="bi bi-send-check me-1"></i> Entregar evaluación y ver nota
      </button>
    </div>`;

  $('#btn-submit')?.addEventListener('click', submit);
}

async function submit() {
  const btn = $('#btn-submit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Calificando en servidor...';

  try {
    const { data } = await api(`/quizzes/${quizId}/attempts/${attemptId}/submit`, { method: 'POST' });
    lastResult = data;

    const approved = data.passed;
    $('#quiz-card').innerHTML = `
      <div class="text-center py-5 animate-fade-in">
        <div class="stat-icon mx-auto mb-3" style="width:80px;height:80px;border-radius:24px;background:${approved ? 'var(--edugt-green-soft)' : '#FEE2E2'};color:${approved ? 'var(--edugt-green)' : '#B91C1C'};font-size:2.4rem;">
          <i class="bi ${approved ? 'bi-trophy-fill' : 'bi-arrow-counterclockwise'}"></i>
        </div>
        <h2 class="h3 fw-bold mb-1">${approved ? '¡Cuestionario Aprobado!' : 'Muy cerca — Sigue practicando'}</h2>
        
        <div class="my-3">
          <span class="display-3 fw-bold" style="color:${approved ? 'var(--edugt-green)' : 'var(--edugt-amber)'}">
            ${data.score}
          </span>
          <span class="fs-4 text-muted fw-bold">/ 100</span>
        </div>

        <div class="d-flex justify-content-center gap-2 mb-3">
          <span class="badge header-stat-pill px-3 py-1.5 rounded-pill" style="font-size:.85rem;">
            <i class="bi bi-journal-text me-1"></i> Intento ${data.attempt_number}
          </span>
          <span class="badge ${approved ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'} px-3 py-1.5 rounded-pill" style="font-size:.85rem;">
            ${approved ? '<i class="bi bi-check-circle-fill me-1"></i> Aprobado' : '<i class="bi bi-x-circle-fill me-1"></i> No alcanzaste el 70%'}
          </span>
        </div>

        ${approved && data.xp_awarded ? `
          <div class="d-inline-flex align-items-center gap-2 badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill mb-3" style="font-size:1.05rem;">
            <i class="bi bi-lightning-charge-fill text-warning"></i> +${data.xp_amount} XP otorgados
          </div>` : ''}

        ${data.new_badges && data.new_badges.length > 0 ? `
          <div class="d-block mb-3">
            <div class="d-inline-flex align-items-center gap-2 badge bg-purple-subtle text-purple border px-3 py-2 rounded-pill shadow-xs" style="font-size:.95rem;background:var(--edugt-primary-soft);color:var(--edugt-primary);border-color:var(--edugt-primary)!important;">
              <i class="bi bi-award-fill"></i> ¡Nueva insignia: ${esc(data.new_badges[0].title)}!
            </div>
          </div>` : ''}

        <p class="text-muted mb-4" style="max-width:480px;margin:0 auto;font-size:.92rem;line-height:1.5;">
          ${approved
            ? '¡Excelente demostración de lógica! Has superado y aprobado este módulo y tu avance ha sido actualizado.'
            : 'Para avanzar al siguiente módulo requieres una nota mínima de 70 puntos. Revisa las explicaciones de las lecciones e inténtalo nuevamente.'}
        </p>

        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="estudiante.html" class="btn btn-edugt btn-lg px-4 shadow-sm">
            <i class="bi bi-map"></i> Volver a mi ruta
          </a>
          ${!approved ? `
            <button type="button" class="btn btn-google btn-lg px-4 shadow-sm" onclick="window.location.reload()">
              <i class="bi bi-arrow-repeat"></i> Reintentar evaluación
            </button>` : ''}
        </div>
      </div>`;
  } catch (err) {
    showAlert(err.message || 'Error al calificar la evaluación');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-check me-1"></i> Entregar evaluación y ver nota';
  }
}

start();
