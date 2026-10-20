/* ==========================================================================
   EduGT — Panel del estudiante (Diseño Inmersivo Gamificado)
   Consume API real; RB-10: nunca calcula notas ni XP en frontend.
   ========================================================================== */
'use strict';

Auth.load();

// Guard: sin sesión → login
if (!Auth.token || !Auth.user) {
  window.location.href = 'index.html';
}

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const TRACK_LABEL = {
  beginner: 'Diagnóstico: Básico',
  intermediate: 'Diagnóstico: Intermedio',
  advanced: 'Diagnóstico: Avanzado',
};

const RANK_NAMES = {
  1: 'Iniciado',
  2: 'Aprendiz',
  3: 'Practicante',
  4: 'Competente',
  5: 'Maestro',
};

/* ---------------- Configuración Temática de Insignias ---------------- */
const BADGE_CONFIG = {
  'novato-logica': {
    title: 'Novato en Lógica',
    icon: 'bi-lightbulb-fill',
    color: '#D97706',
    bgColor: '#FEF3C7',
    border: '#FDE68A',
    category: 'Lógica Inicial',
    shortCondition: 'Tu primer cuestionario con 70% o más.'
  },
  'maestro-pseudocodigo': {
    title: 'Maestro del Pseudocódigo',
    icon: 'bi-code-square',
    color: '#6D4AEF',
    bgColor: '#EDE9FE',
    border: '#DDD6FE',
    category: 'Algoritmos',
    shortCondition: 'Aprobar los 3 cuestionarios de la ruta.'
  },
  'explorador-variables': {
    title: 'Explorador de Variables',
    icon: 'bi-box-seam-fill',
    color: '#0284C7',
    bgColor: '#E0F2FE',
    border: '#BAE6FD',
    category: 'Variables',
    shortCondition: 'El módulo de variables y tipos.'
  },
  'mago-del-control': {
    title: 'Mago del Control',
    icon: 'bi-signpost-split-fill',
    color: '#DB2777',
    bgColor: '#FCE7F3',
    border: '#FBCFE8',
    category: 'Condicionales',
    shortCondition: 'Estructuras de decisión if - else.'
  },
  'maestro-del-bucle': {
    title: 'Maestro del Bucle',
    icon: 'bi-arrow-repeat',
    color: '#16A34A',
    bgColor: '#DCFCE7',
    border: '#BBF7D0',
    category: 'Bucles',
    shortCondition: 'Estructuras cíclicas y bucles.'
  },
  'depurador-elite': {
    title: 'Depurador Élite',
    icon: 'bi-lightning-charge-fill',
    color: '#EA580C',
    bgColor: '#FFEDD5',
    border: '#FED7AA',
    category: 'Precisión 100%',
    shortCondition: 'Aprobar cuestionario en 1er intento.'
  }
};

/* ---------------- Gestión de Pestañas (Tabs) ---------------- */
const VALID_TABS = ['ruta', 'aula', 'insignias', 'ranking'];

window.switchStudentTab = function (tabId) {
  if (!VALID_TABS.includes(tabId)) {
    tabId = 'ruta';
  }

  // Alternar visibilidad de paneles
  VALID_TABS.forEach((id) => {
    const el = $(`#tab-${id}`);
    if (el) {
      el.classList.toggle('d-none', id !== tabId);
    }
  });

  // Actualizar estado activo en sidebar y bottom nav
  $$('[data-tab-target]').forEach((btn) => {
    const isTarget = btn.getAttribute('data-tab-target') === tabId;
    btn.classList.toggle('active', isTarget);
  });

  // Sincronizar hash en la URL
  if (window.location.hash.replace('#', '') !== tabId) {
    history.replaceState(null, '', `#${tabId}`);
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.addEventListener('hashchange', () => {
  const hash = window.location.hash.replace('#', '');
  if (VALID_TABS.includes(hash)) {
    window.switchStudentTab(hash);
  }
});

/* ---------------- Saludo Base ---------------- */
function renderGreeting() {
  $('#pill-track').textContent = TRACK_LABEL[Auth.user.learning_track] ?? 'Completa tu pretest';
}

/* ---------------- XP y Avance ---------------- */
async function loadXp() {
  const { data } = await api('/me/xp');
  const level = data.xp_level || 1;
  const rankName = RANK_NAMES[level] || 'Maestro';
  const remainingXp = Math.max(0, data.next_level_xp - data.xp_total);

  // Cabecera
  $('#top-xp').textContent = `${data.xp_total} XP`;
  $('#top-level').textContent = `Nivel ${level}`;

  // Widget derecho: Tu Avance
  $('#advance-xp').textContent = data.xp_total;
  $('#advance-level').textContent = level;
  $('#advance-rank-name').textContent = rankName;

  // Barra escalonada de niveles (5 segmentos)
  const stepperContainer = $('#advance-level-stepper');
  if (stepperContainer) {
    let segmentsHtml = '';
    for (let i = 1; i <= 5; i++) {
      if (i < level) {
        segmentsHtml += '<div class="level-segment filled"></div>';
      } else if (i === level) {
        segmentsHtml += '<div class="level-segment current"></div>';
      } else {
        segmentsHtml += '<div class="level-segment"></div>';
      }
    }
    stepperContainer.innerHTML = segmentsHtml;
  }

  // Texto explicativo de avance
  $('#advance-xp-desc').innerHTML = `
    Llevas <strong>${data.xp_total}</strong> de <strong>${data.next_level_xp} XP</strong>. 
    Te faltan <strong>${remainingXp} XP</strong> para ${RANK_NAMES[level + 1] ? `Nivel ${level + 1}, ${RANK_NAMES[level + 1]}` : 'el siguiente nivel'}.
    Cada lección suma <strong>10 XP</strong> y cada cuestionario <strong>100 XP</strong>.`;
}

/* ---------------- Racha ---------------- */
async function loadStreak() {
  const { data } = await api('/me/streak');
  $('#advance-streak-count').textContent = data.current;
}

/* ---------------- Misiones / Serpentine Snake Roadmap (RF-8) ---------------- */
async function loadMissions() {
  const { data } = await api('/me/missions');
  const container = $('#missions');
  container.innerHTML = '';

  let totalLessons = 0;
  let doneLessons = 0;
  let activeProgressInfo = null;
  let lastModuleInfo = null;
  let moduleOrdinal = 0;

  if (!data.data || data.data.length === 0) {
    container.innerHTML = `
      <div class="text-center py-4">
        <i class="bi bi-journal-x fs-1 text-muted mb-2"></i>
        <p class="mb-0 text-muted">Aún no hay misiones disponibles para tu ruta de aprendizaje.</p>
      </div>`;
    return;
  }

  const moduleIcons = ['bi-lightbulb-fill', 'bi-code-square', 'bi-box-seam-fill', 'bi-signpost-split-fill', 'bi-arrow-repeat'];
  let modColorIndex = 0;

  for (const mission of data.data) {
    for (const mod of mission.modules) {
      moduleOrdinal++;
      const cleanModTitle = mod.title.replace(/^Módulo\s*\d+\s*[:.-]?\s*/i, '').trim();
      lastModuleInfo = {
        moduleNumber: moduleOrdinal,
        moduleTitle: cleanModTitle,
      };

      // Filtrar lecciones para no duplicar si la última lección es la evaluación del quiz
      const standardLessons = mod.lessons.filter(l => !l.requires_quiz_pass && !l.title.toLowerCase().includes('evaluación'));
      const quizLesson = mod.lessons.find(l => l.requires_quiz_pass || l.title.toLowerCase().includes('evaluación'));
      
      const allSteps = [...standardLessons];
      if (mod.quiz_id || quizLesson) {
        allSteps.push({
          isQuiz: true,
          quiz_id: mod.quiz_id,
          id: quizLesson ? quizLesson.id : null,
          title: 'Evaluación del Módulo',
          completed: Boolean(mod.quiz_passed) || (quizLesson ? quizLesson.completed : false),
          locked: quizLesson ? quizLesson.locked : false,
          estimated_minutes: 10,
          xp_reward: 50
        });
      }

      let modTotal = allSteps.length;
      let modDone = 0;
      const modRows = [];

      allSteps.forEach((step, idx) => {
        totalLessons++;
        if (step.completed) {
          doneLessons++;
          modDone++;
        }

        const hasPretest = Boolean(Auth.user.learning_track);
        const isCompleted = Boolean(step.completed);
        const isLocked = Boolean(step.locked) || !hasPretest;
        const isActive = !isCompleted && !isLocked;

        // Registrar el primer paso pendiente (tanto lección como evaluación del módulo)
        if (!isCompleted && !activeProgressInfo) {
          activeProgressInfo = {
            moduleNumber: moduleOrdinal,
            moduleTitle: cleanModTitle,
            stepTitle: step.title,
            isQuiz: Boolean(step.isQuiz),
          };
        }

        const isRight = (idx % 2 === 1);
        const hasConnector = (idx < allSteps.length - 1);
        const curveColor = isCompleted ? '#86EFAC' : '#E2DEEE';

        let nodeClass = '';
        let nodeIcon = '';
        let statusLabel = '';

        if (step.isQuiz) {
          nodeClass = isCompleted ? 'completed milestone' : isActive ? 'active milestone' : 'milestone';
          nodeIcon = '<i class="bi bi-award-fill"></i>';
          statusLabel = isCompleted
            ? '<span class="text-success fw-bold"><i class="bi bi-patch-check-fill"></i> aprobado</span>'
            : isActive
            ? '<span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> comenzar</span>'
            : '<span class="text-muted"><i class="bi bi-lock-fill"></i> bloqueado</span>';
        } else {
          nodeClass = isCompleted ? 'completed' : isActive ? 'active' : 'locked';
          nodeIcon = isCompleted
            ? '<i class="bi bi-check-lg"></i>'
            : isActive
            ? '<i class="bi bi-play-fill snake-play-glyph"></i>'
            : '<i class="bi bi-lock-fill"></i>';

          statusLabel = isCompleted
            ? '<span class="text-success fw-bold"><i class="bi bi-check2"></i> completado</span>'
            : isActive
            ? '<span class="snake-status-active fw-bold"><i class="bi bi-play-circle-fill me-1"></i> ¡Disponible para empezar!</span>'
            : (!hasPretest && moduleOrdinal === 1 && idx === 0)
            ? '<span class="text-warning fw-bold"><i class="bi bi-lock-fill me-1"></i> Requiere realizar el Pre-test diagnóstico</span>'
            : '<span class="text-muted"><i class="bi bi-lock-fill"></i> bloqueado</span>';
        }

        const stepUrl = step.isQuiz ? `quiz.html?id=${step.quiz_id}` : `leccion.html?id=${step.id}`;
        const titleHtml = isLocked
          ? `<span class="snake-title">${esc(step.title)}</span>`
          : `<a href="${stepUrl}" class="snake-title snake-title-link">${esc(step.title)}</a>`;

        modRows.push(`
          <div class="snake-step-row ${isRight ? 'snake-row-right' : 'snake-row-left'}">
            ${hasConnector ? `
              <svg class="snake-connector-svg" viewBox="0 0 720 136" preserveAspectRatio="none">
                <path d="${isRight ? 'M 540 0 C 540 88, 180 48, 180 136' : 'M 180 0 C 180 88, 540 48, 540 136'}" 
                      stroke="${curveColor}" stroke-width="13" stroke-linecap="round" fill="none" />
              </svg>` : ''}
            
            <div class="snake-node-wrap">
              <a href="${isLocked ? 'javascript:void(0)' : stepUrl}" 
                 class="snake-node ${nodeClass}" 
                 title="${esc(step.title)}">
                ${nodeIcon}
              </a>
            </div>

            <div class="snake-content-card">
              <div>${titleHtml}</div>
              <div class="snake-meta text-muted">
                ${statusLabel} · ${step.estimated_minutes} min ${step.xp_reward ? `· +${step.xp_reward} XP` : ''}
              </div>
            </div>
          </div>`);
      });

      const modCompleted = (modDone === modTotal && modTotal > 0);
      const modRemaining = modTotal - modDone;
      const bannerColorClass = `module-banner-${modColorIndex % 5}`;
      const iconClass = moduleIcons[modColorIndex % moduleIcons.length];
      modColorIndex++;

      container.innerHTML += `
        <div class="mb-4">
          <div class="module-header-banner ${bannerColorClass}">
            <div class="d-flex align-items-center gap-2">
              <i class="bi ${iconClass} fs-5"></i>
              <span class="fs-6">Módulo ${moduleOrdinal}: ${esc(cleanModTitle)}</span>
            </div>
            <span class="module-badge-pill">
              ${modCompleted ? 'completo' : `${modDone} hechas · te queda ${modRemaining}`}
            </span>
          </div>

          <div class="snake-path-section">
            ${modRows.join('')}
          </div>
        </div>`;
    }
  }

  // Hito Final del Roadmap + Tarjeta de Post-test / Graduación y Certificado
  const diag = data.diagnostic_status || {};
  const postUnlocked = Boolean(diag.post_unlocked || (doneLessons === totalLessons && totalLessons > 0 && Auth.user.learning_track));
  const postCompleted = Boolean(diag.post_completed);

  container.innerHTML += `
    <div class="mt-2 pt-3 border-top" style="border-color:var(--edugt-border)!important;">
      <div class="p-3 p-md-4 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-3"
           style="background:var(--edugt-card-subtle);border:2px ${postCompleted ? 'solid var(--edugt-green)' : postUnlocked ? 'solid var(--edugt-primary)' : 'dashed var(--edugt-border)'};">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon shadow-sm flex-shrink-0"
               style="width:52px;height:52px;border-radius:16px;font-size:1.5rem;background:${postCompleted ? 'var(--edugt-green-soft)' : postUnlocked ? 'var(--edugt-primary-soft)' : 'var(--edugt-card)'};color:${postCompleted ? 'var(--edugt-green)' : postUnlocked ? 'var(--edugt-primary)' : 'var(--edugt-muted)'};">
            <i class="bi ${postCompleted ? 'bi-mortarboard-fill' : postUnlocked ? 'bi-flag-fill' : 'bi-lock-fill'}"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <span class="badge rounded-pill px-2.5 py-1 fw-bold"
                    style="font-size:.72rem;background:${postCompleted ? 'var(--edugt-green-soft)' : postUnlocked ? 'var(--edugt-primary-soft)' : 'var(--edugt-card)'};color:${postCompleted ? 'var(--edugt-green)' : postUnlocked ? 'var(--edugt-primary)' : 'var(--edugt-muted)'};border:1px solid var(--edugt-border);">
                ${postCompleted ? '<i class="bi bi-patch-check-fill me-1"></i> HITO FINAL COMPLETADO' : postUnlocked ? '<i class="bi bi-unlock-fill me-1"></i> HITO FINAL DESBLOQUEADO' : '<i class="bi bi-lock-fill me-1"></i> HITO FINAL DE LA RUTA'}
              </span>
              <h3 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">Evaluación Final (Post-test) y Certificación</h3>
            </div>
            <p class="mb-0 text-muted" style="font-size:.86rem;">
              ${postCompleted
                ? `¡Graduado con éxito! Obtuviste <strong>${diag.post_score ?? 0}%</strong> en tu Post-test${diag.gain !== null && diag.gain !== undefined ? ` (${diag.gain >= 0 ? '+' : ''}${diag.gain} pts vs. Pre-test)` : ''}.`
                : postUnlocked
                ? 'Has completado los 3 módulos. Realiza las 14 preguntas finales para medir tu evolución y desbloquear tu Certificado Oficial en PDF.'
                : `Completa las ${totalLessons} etapas de los 3 módulos (llevas ${doneLessons}/${totalLessons}) para desbloquear la evaluación final y tu diploma PDF.`}
            </p>
          </div>
        </div>
        <div>
          ${postCompleted ? `
            <button type="button" class="btn btn-edugt px-4 shadow-sm" onclick="window.downloadStudentCertificate(this)">
              <i class="bi bi-award-fill me-1"></i> Descargar Certificado PDF
            </button>
          ` : postUnlocked ? `
            <a href="diagnostico.html?kind=post" class="btn btn-edugt px-4 shadow-sm">
              <i class="bi bi-play-circle-fill me-1"></i> Realizar Post-test Final
            </a>
          ` : `
            <span class="pill-edugt pill-locked"><i class="bi bi-lock-fill me-1"></i> Bloqueado (${doneLessons}/${totalLessons})</span>
          `}
        </div>
      </div>
    </div>`;

  renderPosttestAndGraduationCard(diag, postUnlocked, postCompleted);

  // Actualizar cabecera principal unificada con Módulo y Lección exacta
  const firstName = Auth.user.name ? Auth.user.name.split(' ')[0] : 'Estudiante';
  if (!Auth.user.learning_track) {
    $('#greeting').textContent = `¡Hola, ${firstName}! Completa tu Pre-test Diagnóstico para comenzar`;
    $('#greeting-sub').innerHTML = `<strong>Requisito inicial:</strong> Realiza primero la prueba diagnóstica de arriba para desbloquear el <strong>Módulo 1: Variables y Tipos de Datos</strong>.`;
  } else if (activeProgressInfo) {
    const stepPrefix = activeProgressInfo.isQuiz ? 'Evaluación actual' : 'Lección actual';
    $('#greeting').textContent = `¡Hola, ${firstName}! Vas por el Módulo ${activeProgressInfo.moduleNumber}: ${activeProgressInfo.moduleTitle}`;
    $('#greeting-sub').innerHTML = `<strong>${stepPrefix}:</strong> ${esc(activeProgressInfo.stepTitle)} · Llevas ${doneLessons} de ${totalLessons} etapas completadas.`;
  } else if (lastModuleInfo && !postCompleted) {
    $('#greeting').textContent = `¡Hola, ${firstName}! Completaste los ${lastModuleInfo.moduleNumber} módulos · Realiza tu Post-test Final`;
    $('#greeting-sub').innerHTML = `Completaste las <strong>${doneLessons} de ${totalLessons} etapas</strong>. Da el último paso con la <strong>Evaluación Final (Post-test)</strong> arriba para obtener tu Certificado Oficial.`;
  } else if (lastModuleInfo && postCompleted) {
    $('#greeting').textContent = `¡Felicidades, ${firstName}! Has completado toda la Ruta de EduGT 🎓`;
    $('#greeting-sub').innerHTML = `Programa finalizado al <strong>100%</strong> (${doneLessons}/${totalLessons} etapas + Post-test: <strong>${diag.post_score ?? 0}%</strong>). Ya puedes descargar tu Certificado Oficial en PDF.`;
  }
}

/* ---------------- Tarjeta Superior de Post-test / Graduación y Certificado PDF ---------------- */
function renderPosttestAndGraduationCard(diag, postUnlocked, postCompleted) {
  const wrap = $('#posttest-graduation-wrap');
  if (!wrap) return;

  if (!postUnlocked && !postCompleted) {
    wrap.innerHTML = '';
    return;
  }

  if (postUnlocked && !postCompleted) {
    wrap.innerHTML = `
      <div class="card-edugt p-4 mb-4 animate-fade-in" style="border: 2px solid var(--edugt-primary);">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="stat-icon shadow-sm flex-shrink-0" style="width:54px;height:54px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:1.6rem;border-radius:16px;">
              <i class="bi bi-flag-fill"></i>
            </div>
            <div>
              <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge bg-success text-white fw-bold px-2.5 py-1 rounded-pill" style="font-size:.72rem;">
                  <i class="bi bi-unlock-fill me-1"></i> PASO FINAL DESBLOQUEADO
                </span>
                <h2 class="h5 fw-bold mb-0" style="color:var(--edugt-text);">Evaluación Diagnóstica Final (Post-test)</h2>
              </div>
              <p class="mb-0" style="color:var(--edugt-muted);font-size:.9rem;">
                ¡Completaste los 3 módulos! Responde estas 14 preguntas finales para comparar tu avance respecto al Pre-test${diag.pre_score !== null && diag.pre_score !== undefined ? ` (obtuviste <strong>${diag.pre_score}%</strong> al inicio)` : ''} y habilitar tu <strong>Certificado Oficial en PDF</strong>.
              </p>
            </div>
          </div>
          <a href="diagnostico.html?kind=post" class="btn btn-edugt btn-lg px-4 shadow-sm">
            <i class="bi bi-play-circle-fill me-1"></i> Realizar Post-test ahora
          </a>
        </div>
      </div>`;
    return;
  }

  // Estado Graduado (postCompleted === true)
  const preScore = diag.pre_score;
  const postScore = diag.post_score ?? 0;
  const gain = diag.gain;
  const dims = Array.isArray(diag.dimensions) ? diag.dimensions : [];

  const dimsRowsHtml = dims.map((d) => {
    const preVal = d.pre_score ?? 0;
    const postVal = d.post_score ?? 0;
    const delta = d.delta;
    const deltaBadge = delta !== null && delta !== undefined
      ? `<span class="badge rounded-pill px-2 py-0.5" style="font-size:.72rem;background:${delta >= 0 ? 'var(--edugt-green-soft)' : '#FEE2E2'};color:${delta >= 0 ? 'var(--edugt-green)' : '#DC2626'};">${delta >= 0 ? '+' : ''}${delta}%</span>`
      : '';
    return `
      <div class="col-12 col-md-6">
        <div class="p-2.5 rounded-3 border" style="background:var(--edugt-card);border-color:var(--edugt-border)!important;">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="fw-bold" style="font-size:.82rem;color:var(--edugt-text);">${esc(d.label)}</span>
            <div class="d-flex align-items-center gap-1.5">
              <small class="text-muted" style="font-size:.76rem;">Pre: ${d.pre_score !== null ? `${preVal}%` : '—'} → Post: <strong>${postVal}%</strong></small>
              ${deltaBadge}
            </div>
          </div>
          <div class="progress-edugt" style="height:7px;">
            <div class="bar" style="width:${Math.max(0, Math.min(100, postVal))}%;"></div>
          </div>
        </div>
      </div>`;
  }).join('');

  wrap.innerHTML = `
    <div class="card-edugt p-4 mb-4 animate-fade-in" style="border: 2px solid var(--edugt-green);">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
          <div class="stat-icon shadow-sm flex-shrink-0" style="width:56px;height:56px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.75rem;border-radius:18px;">
            <i class="bi bi-mortarboard-fill"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
              <span class="badge rounded-pill px-2.5 py-1 fw-bold" style="font-size:.72rem;background:var(--edugt-green-soft);color:var(--edugt-green);border:1px solid var(--edugt-green);">
                <i class="bi bi-patch-check-fill me-1"></i> PROGRAMA COMPLETADO · FOLIO ${esc(diag.certificate_folio || 'EDUGT')}
              </span>
              <h2 class="h5 fw-bold mb-0" style="color:var(--edugt-text);">Tu Evolución Diagnóstica y Certificado Oficial</h2>
            </div>
            <p class="mb-0" style="color:var(--edugt-muted);font-size:.88rem;">
              Has culminado los 3 módulos y la evaluación final. Descarga tu diploma oficial en PDF o revisa tu crecimiento por dimensión.
            </p>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button type="button" class="btn btn-google btn-sm px-3" onclick="document.getElementById('student-dims-collapse')?.classList.toggle('d-none')">
            <i class="bi bi-bar-chart-line me-1"></i> Ver mis 7 dimensiones
          </button>
          <button type="button" class="btn btn-edugt px-4 shadow-sm" onclick="window.downloadStudentCertificate(this)">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar Certificado (PDF)
          </button>
        </div>
      </div>

      <!-- KPIs Comparativos Pre-test vs Post-test -->
      <div class="row g-3">
        <div class="col-4">
          <div class="p-3 rounded-4 border text-center h-100" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:.7rem;letter-spacing:.04em;">Pre-test Inicial</small>
            <div class="h4 fw-bold mb-0 mt-1" style="color:var(--edugt-text);">${preScore !== null && preScore !== undefined ? `${preScore}%` : '—'}</div>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 rounded-4 border text-center h-100" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:.7rem;letter-spacing:.04em;">Post-test Final</small>
            <div class="h4 fw-bold mb-0 mt-1" style="color:var(--edugt-primary);">${postScore}%</div>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 rounded-4 border text-center h-100" style="background:var(--edugt-card-subtle);border-color:var(--edugt-border)!important;">
            <small class="text-muted text-uppercase fw-bold d-block" style="font-size:.7rem;letter-spacing:.04em;">Mejora Alcanzada</small>
            <div class="h4 fw-bold mb-0 mt-1" style="color:${gain !== null && gain >= 0 ? 'var(--edugt-green)' : 'var(--edugt-text)'};">
              ${gain !== null && gain !== undefined ? `${gain >= 0 ? '+' : ''}${gain} pts` : 'Completado'}
            </div>
          </div>
        </div>
      </div>

      <!-- Desglose en las 7 dimensiones (desplegable) -->
      <div id="student-dims-collapse" class="mt-3 pt-3 border-top d-none" style="border-color:var(--edugt-border)!important;">
        <h3 class="h6 fw-bold mb-2" style="font-size:.88rem;color:var(--edugt-text);">
          <i class="bi bi-diagram-3-fill text-primary me-1"></i> Comparativa Pre-test vs. Post-test en las 7 Dimensiones
        </h3>
        <div class="row g-2">
          ${dimsRowsHtml}
        </div>
      </div>
    </div>`;
}

window.downloadStudentCertificate = async function (btnEl) {
  const origHtml = btnEl ? btnEl.innerHTML : '';
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
    if (!res.ok) {
      throw new Error('No fue posible generar tu certificado en este momento.');
    }
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
    alert(err.message || 'Error al descargar el certificado PDF');
  } finally {
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerHTML = origHtml;
    }
  }
};

/* ---------------- Insignias Temáticas (RF-17) ---------------- */
function getLocallyDismissedBadgeIds() {
  try {
    return JSON.parse(localStorage.getItem('edugt_dismissed_badges') || '[]');
  } catch {
    return [];
  }
}

function markBadgeDismissedLocally(badgeId) {
  try {
    const list = getLocallyDismissedBadgeIds();
    if (!list.includes(badgeId)) {
      list.push(badgeId);
      localStorage.setItem('edugt_dismissed_badges', JSON.stringify(list));
    }
  } catch (e) {
    console.warn('Error guardando en localStorage:', e);
  }
}

window.dismissBadgeCelebration = async function(badgeId) {
  const celebrationWrap = $('#celebration-banner-wrap');
  if (celebrationWrap) {
    celebrationWrap.innerHTML = '';
  }

  const idsToDismiss = window._pendingUnnotifiedBadgeIds && window._pendingUnnotifiedBadgeIds.length > 0
    ? window._pendingUnnotifiedBadgeIds
    : (badgeId ? [badgeId] : []);

  idsToDismiss.forEach(markBadgeDismissedLocally);
  window._pendingUnnotifiedBadgeIds = [];

  try {
    await api('/me/badges/dismiss', {
      method: 'POST',
      body: JSON.stringify({ badge_ids: idsToDismiss }),
    });
  } catch (err) {
    console.warn('Error al descartar notificación de insignia en servidor:', err);
  }
};

window.viewAndDismissBadge = async function(badgeId) {
  await window.dismissBadgeCelebration(badgeId);
  window.switchStudentTab('insignias');
};

async function loadBadges() {
  const { data } = await api('/me/badges');

  const obtained = data.obtained || [];
  const locked = data.locked || [];
  const totalBadges = obtained.length + locked.length || 6;

  $('#top-badges-count').textContent = `${obtained.length} de ${totalBadges} insignias`;

  // Banner superior de celebración:
  // Solo se muestra UNA VEZ cuando una insignia es RECIÉN conseguida (unnotified)
  // y al cerrarse, no vuelve a mostrarse jamás.
  const celebrationWrap = $('#celebration-banner-wrap');
  if (celebrationWrap) {
    const dismissedLocally = getLocallyDismissedBadgeIds();
    const freshBadges = (data.unnotified || []).filter(b => !dismissedLocally.includes(b.id));

    if (freshBadges.length > 0) {
      window._pendingUnnotifiedBadgeIds = freshBadges.map(b => b.id);
      const latest = freshBadges[freshBadges.length - 1];
      const config = BADGE_CONFIG[latest.slug] || {
        icon: 'bi-award-fill',
        color: '#D97706',
        category: 'Logro'
      };

      celebrationWrap.innerHTML = `
        <div class="celebration-banner p-3 p-md-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3" data-badge-id="${latest.id}">
          <div class="d-flex align-items-center gap-3">
            <div class="celebration-icon shadow-sm">
              <i class="bi ${config.icon}"></i>
            </div>
            <div>
              <small class="text-uppercase fw-bold celebration-kicker" style="letter-spacing:.05em;font-size:.75rem;">¡Nueva Insignia Conseguida!</small>
              <h3 class="fw-bold mb-0 celebration-title" style="font-size:1.1rem;">${esc(latest.title)}</h3>
              <p class="mb-0 celebration-desc text-muted" style="font-size:.85rem;">${esc(latest.description)}</p>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-celebration fw-bold" onclick="window.viewAndDismissBadge(${latest.id})">
              Ver mi insignia
            </button>
            <button type="button" class="btn-close" aria-label="Cerrar" title="Cerrar notificación" onclick="window.dismissBadgeCelebration(${latest.id})"></button>
          </div>
        </div>`;
    } else {
      celebrationWrap.innerHTML = '';
      window._pendingUnnotifiedBadgeIds = [];
    }
  }

  // 1. Widget Derecho: Mini lista de Insignias
  const miniList = $('#mini-badges-list');
  if (miniList) {
    miniList.innerHTML = '';

    const allSlugs = Object.keys(BADGE_CONFIG);
    allSlugs.forEach((slug, idx) => {
      const cfg = BADGE_CONFIG[slug];
      const matchedBadge = obtained.find((b) => b.slug === slug) || locked.find((b) => b.slug === slug);
      const badgeTitle = matchedBadge?.title || cfg.title || cfg.category;
      const isObtained = obtained.some((b) => b.slug === slug);
      const isCurrentInGame = !isObtained && (idx === obtained.length);

      const statusTag = isObtained
        ? '<span class="badge-tag-conseguida">CONSEGUIDA</span>'
        : isCurrentInGame
        ? '<span class="badge-tag-enjuego">EN JUEGO</span>'
        : '<span class="badge-tag-bloqueada">BLOQUEADA</span>';

      const rowClass = isObtained ? 'conseguida' : isCurrentInGame ? 'en-juego' : 'bloqueada';

      miniList.innerHTML += `
        <div class="mini-badge-row ${rowClass}">
          <div class="mini-badge-icon shadow-2xs">
            <i class="bi ${cfg.icon}"></i>
          </div>
          <div class="flex-grow-1" style="min-width:0;">
            <div class="d-flex justify-content-between align-items-center mb-0.5">
              <h5 class="fw-bold mb-0 text-truncate mini-badge-title" style="font-size:.88rem;color:var(--edugt-text);">${esc(badgeTitle)}</h5>
              ${statusTag}
            </div>
            <small class="text-muted d-block text-truncate" style="font-size:.76rem;">${esc(cfg.shortCondition)}</small>
          </div>
        </div>`;
    });
  }

  // 2. Tab Dedicada de Insignias: Catálogo Completo
  const gridContainer = $('#badges-grid');
  if (gridContainer) {
    gridContainer.innerHTML = '';

    const unlockedCount = obtained.length;
    const percent = Math.round((unlockedCount / totalBadges) * 100);

    $('#badges-unlocked-title').textContent = `${unlockedCount} de ${totalBadges} insignias obtenidas`;
    $('#badges-progress-bar').style.width = `${percent}%`;
    $('#badges-progress-percent').textContent = `${percent}%`;

    obtained.forEach((b) => {
      const config = BADGE_CONFIG[b.slug] || {
        icon: 'bi-award-fill',
        category: 'Insignia'
      };

      gridContainer.innerHTML += `
        <div class="col-12 col-md-6 col-lg-4">
          <div class="badge-card unlocked h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge rounded-pill badge-category-pill">
                  ${config.category}
                </span>
                <span class="pill-edugt pill-done" style="font-size:.72rem;">
                  <i class="bi bi-patch-check-fill"></i> Desbloqueada
                </span>
              </div>
              <div class="badge-icon-lg shadow-sm">
                <i class="bi ${config.icon}"></i>
              </div>
              <h4 class="fw-bold text-center mb-1" style="font-size:1.05rem;color:var(--edugt-text);">${esc(b.title)}</h4>
              <p class="text-center mb-3" style="color:var(--edugt-muted);font-size:.85rem;line-height:1.4;">
                ${esc(b.description)}
              </p>
            </div>
            <div class="text-center pt-2 border-top" style="border-color:var(--edugt-border)!important;">
              <small class="text-success fw-bold" style="font-size:.78rem;">
                <i class="bi bi-stars"></i> ¡Completado con éxito!
              </small>
            </div>
          </div>
        </div>`;
    });

    locked.forEach((b) => {
      const config = BADGE_CONFIG[b.slug] || {
        icon: 'bi-award-fill',
        category: 'Desafío'
      };

      gridContainer.innerHTML += `
        <div class="col-12 col-md-6 col-lg-4">
          <div class="badge-card locked h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge rounded-pill badge-category-pill-locked">
                  ${config.category}
                </span>
                <span class="pill-edugt pill-locked" style="font-size:.72rem;">
                  <i class="bi bi-lock-fill"></i> Bloqueada
                </span>
              </div>
              <div class="badge-icon-lg">
                <i class="bi ${config.icon}"></i>
              </div>
              <h4 class="fw-bold text-center mb-1 text-muted" style="font-size:1.05rem;">${esc(b.title)}</h4>
              <p class="text-center mb-3 text-muted" style="font-size:.85rem;line-height:1.4;">
                ${esc(b.description)}
              </p>
            </div>
            <div class="text-center pt-2 border-top" style="border-color:var(--edugt-border)!important;">
              <small class="text-muted" style="font-size:.78rem;">
                <i class="bi bi-lock me-1"></i>Requisito pendiente
              </small>
            </div>
          </div>
        </div>`;
    });
  }
}

/* ---------------- Ranking (RF-18) — Global y Por Aula ---------------- */
let rankingScope = 'global';
let selectedClassroomId = null;
let enrolledClassrooms = [];

function updateRankingClassroomPicker() {
  const pickerWrap = $('#ranking-classroom-picker');
  const select = $('#ranking-classroom-select');
  if (!pickerWrap || !select) return;

  if (rankingScope === 'classroom' && enrolledClassrooms.length > 0) {
    pickerWrap.classList.remove('d-none');
    select.innerHTML = enrolledClassrooms.map(c => 
      `<option value="${c.id}" ${selectedClassroomId === c.id ? 'selected' : ''}>${esc(c.name)}</option>`
    ).join('');
    if (!selectedClassroomId && enrolledClassrooms.length > 0) {
      selectedClassroomId = enrolledClassrooms[0].id;
    }
    if (selectedClassroomId) {
      select.value = selectedClassroomId;
    }
  } else {
    pickerWrap.classList.add('d-none');
  }
}

async function loadRankings(targetClassroomId = null) {
  if (targetClassroomId) {
    selectedClassroomId = targetClassroomId;
  }

  let url = `/rankings?scope=${rankingScope}`;
  if (rankingScope === 'classroom' && selectedClassroomId) {
    url += `&classroom_id=${selectedClassroomId}`;
  }

  const { data } = await api(url);
  const list = data.data || [];

  if (data.classrooms && data.classrooms.length > 0) {
    enrolledClassrooms = data.classrooms;
  }

  if (rankingScope === 'classroom') {
    if (data.classroom_id) {
      selectedClassroomId = data.classroom_id;
    }
    updateRankingClassroomPicker();
    const classroomName = data.classroom_name || enrolledClassrooms.find(c => c.id === selectedClassroomId)?.name || 'Mi Aula';
    $('#ranking-scope-label').textContent = `Aula: ${classroomName}`;
  } else {
    updateRankingClassroomPicker();
    $('#ranking-scope-label').textContent = 'Ámbito Global';
  }

  const podiumContainer = $('#podium-container');
  const rankingsContainer = $('#rankings');

  if (list.length > 0) {
    podiumContainer.classList.remove('d-none');
    podiumContainer.innerHTML = '';

    const top1 = list[0];
    const top2 = list[1] || null;
    const top3 = list[2] || null;

    const renderPodiumItem = (student, rankClass, rankBadgeClass, vectorIconClass, rankLabel, iconName, rankNum, colSize) => {
      if (!student) return '';
      const isMe = student.user_id === Auth.user.id;
      const avatarUrl = student.avatar_url || (isMe ? Auth.user?.avatar_url : null);
      const avatarInner = avatarUrl
        ? `<img src="${esc(avatarUrl)}" alt="${esc(student.name)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">`
        : `<span class="fw-bold" style="font-size:1.2rem;">${student.name.charAt(0).toUpperCase()}</span>`;
      return `
        <div class="col-md-${colSize}">
          <div class="podium-card ${rankClass} h-100 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge rounded-pill podium-rank-badge ${rankBadgeClass} fw-bold px-2.5 py-1" style="font-size:.75rem;">
                <i class="bi ${iconName} me-1"></i> ${rankLabel}
              </span>
              <span class="podium-vector-icon ${vectorIconClass}">
                <i class="bi ${iconName}"></i>
              </span>
            </div>
            <div class="podium-avatar shadow-sm" style="overflow:hidden;padding:0;">
              ${avatarInner}
            </div>
            <h4 class="fw-bold mb-1 podium-name" style="font-size:1.05rem;color:var(--edugt-text);">
              ${esc(student.name)}${isMe ? ' <span class="badge bg-primary" style="font-size:.7rem;">Tú</span>' : ''}
            </h4>
            <div class="d-flex justify-content-center align-items-center gap-2 podium-meta" style="font-size:.85rem;color:var(--edugt-muted);">
              <span>Nv. ${student.xp_level}</span>
              <span>·</span>
              <span class="fw-bold podium-xp">${student.xp_total} XP</span>
            </div>
          </div>
        </div>`;
    };

    let podiumHtml = '';
    if (top2) podiumHtml += renderPodiumItem(top2, 'podium-2', 'rank-2-badge', 'silver-icon', '2° Lugar', 'bi-award-fill', 2, 4);
    if (top1) podiumHtml += renderPodiumItem(top1, 'podium-1', 'rank-1-badge', 'gold-icon', '1° Lugar', 'bi-award-fill', 1, 4);
    if (top3) podiumHtml += renderPodiumItem(top3, 'podium-3', 'rank-3-badge', 'bronze-icon', '3° Lugar', 'bi-award-fill', 3, 4);

    podiumContainer.innerHTML = podiumHtml;
  } else {
    podiumContainer.classList.add('d-none');
  }

  const rows = list.map((r, i) => {
    let rankBadgeHtml = '';
    if (i === 0) {
      rankBadgeHtml = `<div class="rank-pos-badge rank-gold" title="1° Lugar">1</div>`;
    } else if (i === 1) {
      rankBadgeHtml = `<div class="rank-pos-badge rank-silver" title="2° Lugar">2</div>`;
    } else if (i === 2) {
      rankBadgeHtml = `<div class="rank-pos-badge rank-bronze" title="3° Lugar">3</div>`;
    } else {
      rankBadgeHtml = `<div class="rank-pos-badge rank-default">${r.position}</div>`;
    }
    const me = r.user_id === Auth.user.id;
    const rowAvatarUrl = r.avatar_url || (me ? Auth.user?.avatar_url : null);
    const rowAvatarHtml = rowAvatarUrl
      ? `<img src="${esc(rowAvatarUrl)}" alt="${esc(r.name)}" width="34" height="34" style="width:34px;height:34px;object-fit:cover;border-radius:10px;flex-shrink:0;" loading="lazy">`
      : `<div class="d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:34px;height:34px;border-radius:10px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:.85rem;">${esc((r.name || 'E').charAt(0).toUpperCase())}</div>`;

    return `
      <div class="ranking-row d-flex align-items-center gap-3 border rounded-4 px-3 py-2.5 ${me ? 'is-me' : ''}">
        <div style="width:36px;display:flex;align-items:center;justify-content:center;">${rankBadgeHtml}</div>
        ${rowAvatarHtml}
        <div class="flex-grow-1">
          <span style="font-size:.95rem;color:var(--edugt-text);font-weight:600;">${esc(r.name)}</span>
          ${me ? '<span class="badge bg-primary ms-2" style="font-size:.7rem;">Tú</span>' : ''}
        </div>
        <div class="text-muted" style="font-size:.85rem;">Nivel ${r.xp_level}</div>
        <div class="fw-bold text-end ranking-xp-val" style="min-width:76px;font-size:.95rem;color:var(--edugt-primary);">
          ${r.xp_total} XP
        </div>
      </div>`;
  });

  rankingsContainer.innerHTML = rows.join('') || (rankingScope === 'classroom'
    ? `<div class="text-center py-4">
        <i class="bi bi-people text-muted fs-2 mb-2"></i>
        <h5 class="h6 fw-bold mb-1" style="color:var(--edugt-text);">No hay estudiantes en esta aula</h5>
        <p class="mb-0 text-muted" style="font-size:.85rem;">Aún no se han registrado compañeros en esta aula.</p>
      </div>`
    : `<div class="text-center py-4">
        <i class="bi bi-people text-muted fs-2 mb-2"></i>
        <p class="mb-0 text-muted">Aún no hay puntajes registrados en este ámbito.</p>
      </div>`);
}

$('#rk-global')?.addEventListener('click', async () => {
  rankingScope = 'global';
  $('#rk-global').className = 'btn btn-edugt';
  $('#rk-classroom').className = 'btn btn-google';
  updateRankingClassroomPicker();
  await loadRankings();
});

$('#rk-classroom')?.addEventListener('click', async () => {
  rankingScope = 'classroom';
  $('#rk-classroom').className = 'btn btn-edugt';
  $('#rk-global').className = 'btn btn-google';
  if (!selectedClassroomId && enrolledClassrooms.length > 0) {
    selectedClassroomId = enrolledClassrooms[0].id;
  }
  updateRankingClassroomPicker();
  await loadRankings(selectedClassroomId);
});

$('#ranking-classroom-select')?.addEventListener('change', async (e) => {
  selectedClassroomId = parseInt(e.target.value, 10);
  await loadRankings(selectedClassroomId);
});

/* ---------------- Aulas del Estudiante (RF-19) ---------------- */
async function loadStudentClassrooms() {
  const container = $('#student-classrooms-list');
  const countBadge = $('#enrolled-classrooms-count');
  if (!container) return;

  try {
    const { data } = await api('/classrooms');
    const classrooms = data.data || [];
    enrolledClassrooms = classrooms;

    if (countBadge) {
      countBadge.textContent = `${classrooms.length} ${classrooms.length === 1 ? 'aula' : 'aulas'}`;
    }

    updateRankingClassroomPicker();

    if (classrooms.length === 0) {
      container.innerHTML = `
        <div class="student-empty-classroom-box text-center py-4 px-3">
          <div class="stat-icon mx-auto mb-2" style="width:48px;height:48px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:1.3rem;border-radius:14px;">
            <i class="bi bi-people"></i>
          </div>
          <h4 class="h6 fw-bold mb-1" style="color:var(--edugt-text);">Aún no estás inscrito en ningún aula</h4>
          <p class="text-muted mb-0" style="font-size:.84rem;max-width:340px;margin:0 auto;">
            Pídele a tu docente el código de inscripción de 8 caracteres e ingrésalo en el formulario de la izquierda.
          </p>
        </div>`;
      return;
    }

    container.innerHTML = classrooms.map(c => `
      <div class="card-edugt student-classroom-card p-3 border">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="d-flex align-items-center gap-2.5">
            <div class="stat-icon" style="width:42px;height:42px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.2rem;border-radius:12px;">
              <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
              <h4 class="h6 fw-bold mb-0" style="color:var(--edugt-text);">${esc(c.name)}</h4>
              <small class="text-muted"><i class="bi bi-person-fill text-primary"></i> ${esc(c.teacher_name ? `Prof. ${c.teacher_name}` : 'Docente asignado')}</small>
            </div>
          </div>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
            <i class="bi bi-check-circle-fill me-1"></i> Inscrito
          </span>
        </div>
        
        <div class="d-flex align-items-center gap-3 text-muted mb-3 flex-wrap" style="font-size:.82rem;">
          ${c.institution ? `<span><i class="bi bi-building me-1"></i> ${esc(c.institution)}</span>` : ''}
          ${c.grade_level ? `<span><i class="bi bi-bookmark me-1"></i> ${esc(c.grade_level)}</span>` : ''}
          <span><i class="bi bi-calendar3 me-1"></i> Ciclo ${esc(c.academic_year)}</span>
        </div>

        <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold" onclick="window.viewClassroomRanking(${c.id})">
          <i class="bi bi-trophy-fill me-1 text-warning"></i> Ver ranking de ${esc(c.name)}
        </button>
      </div>
    `).join('');
  } catch (err) {
    console.error('Error cargando aulas:', err);
    container.innerHTML = `<div class="alert alert-danger py-2 mb-0" style="font-size:.85rem;">Error al cargar las aulas inscritas.</div>`;
  }
}

window.viewClassroomRanking = function (classroomId) {
  rankingScope = 'classroom';
  if (classroomId) {
    selectedClassroomId = classroomId;
  }
  window.switchStudentTab('ranking');
  $('#rk-classroom').className = 'btn btn-edugt';
  $('#rk-global').className = 'btn btn-google';
  updateRankingClassroomPicker();
  const select = $('#ranking-classroom-select');
  if (select && selectedClassroomId) {
    select.value = selectedClassroomId;
  }
  loadRankings(selectedClassroomId);
};

$('#form-join-classroom')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const alertEl = $('#join-classroom-alert');
  const btn = $('#btn-join-classroom');
  const input = $('#input-classroom-code');
  const code = input.value.trim().toUpperCase();

  if (!code) return;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Inscribiendo...';
  if (alertEl) alertEl.classList.add('d-none');

  try {
    await api('/classrooms/join', {
      method: 'POST',
      body: { code },
    });

    if (alertEl) {
      alertEl.className = 'alert alert-success py-2 px-3 mb-3';
      alertEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ¡Te has unido al aula exitosamente!';
      alertEl.classList.remove('d-none');
    }
    input.value = '';
    await loadStudentClassrooms();
  } catch (err) {
    if (alertEl) {
      alertEl.className = 'alert alert-danger py-2 px-3 mb-3';
      alertEl.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1"></i> ${esc(err.message || 'Código de aula inválido')}`;
      alertEl.classList.remove('d-none');
    }
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-door-open-fill me-1"></i> Unirme al aula';
  }
});

const codeInput = $('#input-classroom-code');
if (codeInput) {
  const origPlaceholder = codeInput.placeholder || 'EJ: ABC12345';
  codeInput.addEventListener('focus', () => {
    codeInput.placeholder = '';
  });
  codeInput.addEventListener('blur', () => {
    if (!codeInput.value.trim()) {
      codeInput.placeholder = origPlaceholder;
    }
  });
}

/* ---------------- Logout ---------------- */
async function logout() {
  try {
    await api('/auth/logout', { method: 'POST' });
  } catch {
    /* token ya inválido */
  }
  Auth.clear();
  window.location.href = 'index.html';
}

$('#btn-logout')?.addEventListener('click', logout);
$('#btn-logout-m')?.addEventListener('click', logout);

/* ---------------- CTA pretest (una sola acción dominante al iniciar) ---------------- */
function renderPretestCta() {
  const hasTrack = Boolean(Auth.user.learning_track);
  $('#cta-pretest')?.classList.toggle('d-none', hasTrack);
  $('#btn-pretest')?.addEventListener('click', () => {
    window.location.href = 'diagnostico.html?kind=pre';
  });
}

/* ---------------- Boot ---------------- */
(async function boot() {
  renderGreeting();
  renderPretestCta();

  const initialTab = window.location.hash.replace('#', '') || 'ruta';
  window.switchStudentTab(initialTab);

  try {
    await Promise.all([loadXp(), loadStreak()]);
    await Promise.all([loadMissions(), loadBadges(), loadRankings(), loadStudentClassrooms()]);
  } catch (err) {
    if (err.status === 401) {
      Auth.clear();
      window.location.href = 'index.html';
      return;
    }
    console.error('Error al inicializar el panel:', err);
  }
})();
