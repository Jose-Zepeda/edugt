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
  beginner: 'Ruta básica',
  intermediate: 'Ruta intermedia',
  advanced: 'Ruta avanzada',
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
    icon: 'bi-lightbulb-fill',
    color: '#D97706',
    bgColor: '#FEF3C7',
    border: '#FDE68A',
    category: 'Lógica Inicial',
    shortCondition: 'Tu primer cuestionario con 70% o más.'
  },
  'maestro-pseudocodigo': {
    icon: 'bi-code-square',
    color: '#6D4AEF',
    bgColor: '#EDE9FE',
    border: '#DDD6FE',
    category: 'Algoritmos',
    shortCondition: 'Aprobar 5 cuestionarios con éxito.'
  },
  'explorador-variables': {
    icon: 'bi-box-seam-fill',
    color: '#0284C7',
    bgColor: '#E0F2FE',
    border: '#BAE6FD',
    category: 'Variables',
    shortCondition: 'El módulo de variables y tipos.'
  },
  'mago-del-control': {
    icon: 'bi-signpost-split-fill',
    color: '#DB2777',
    bgColor: '#FCE7F3',
    border: '#FBCFE8',
    category: 'Condicionales',
    shortCondition: 'Estructuras de decisión si-entonces.'
  },
  'maestro-del-bucle': {
    icon: 'bi-arrow-repeat',
    color: '#16A34A',
    bgColor: '#DCFCE7',
    border: '#BBF7D0',
    category: 'Bucles',
    shortCondition: 'Estructuras cíclicas y bucles.'
  },
  'depurador-elite': {
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
  let currentActiveModule = 1;

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
          completed: quizLesson ? quizLesson.completed : false,
          locked: quizLesson ? quizLesson.locked : false,
          estimated_minutes: 10,
          xp_reward: 100
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

        const isCompleted = Boolean(step.completed);
        const isLocked = Boolean(step.locked);
        const isActive = !isCompleted && !isLocked;

        if (isActive && !step.isQuiz) {
          currentActiveModule = mod.id;
        }

        const isRight = (idx % 2 === 1);
        const hasConnector = (idx < allSteps.length - 1);
        const curveColor = isCompleted ? '#86EFAC' : '#E2DEEE';

        let nodeClass = '';
        let nodeIcon = '';
        let statusLabel = '';
        let actionBtn = '';

        if (step.isQuiz) {
          nodeClass = isCompleted ? 'completed milestone' : isActive ? 'active milestone' : 'milestone';
          nodeIcon = '<i class="bi bi-award-fill"></i>';
          statusLabel = isCompleted
            ? '<span class="text-success fw-bold"><i class="bi bi-patch-check-fill"></i> aprobada</span>'
            : isActive
            ? '<span class="text-warning fw-bold"><i class="bi bi-star-fill"></i> listo para presentar</span>'
            : '<span class="text-muted"><i class="bi bi-lock-fill"></i> bloqueada</span>';
        } else {
          nodeClass = isCompleted ? 'completed' : isActive ? 'active' : 'locked';
          nodeIcon = isCompleted
            ? '<i class="bi bi-check-lg"></i>'
            : isActive
            ? '<i class="bi bi-play-fill"></i>'
            : '<i class="bi bi-lock-fill"></i>';

          statusLabel = isCompleted
            ? '<span class="text-success fw-bold"><i class="bi bi-check2"></i> completada</span>'
            : isActive
            ? '<span class="text-primary fw-bold"><i class="bi bi-lightning-charge-fill"></i> disponible ahora</span>'
            : '<span class="text-muted"><i class="bi bi-lock-fill"></i> bloqueada</span>';
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
              <span class="fs-6">${esc(mod.title)}</span>
            </div>
            <span class="module-badge-pill">
              ${modCompleted ? 'completa' : `${modDone} hechas · te queda ${modRemaining}`}
            </span>
          </div>

          <div class="snake-path-section">
            ${modRows.join('')}
          </div>
        </div>`;
    }
  }

  // Actualizar cabecera principal unificada
  const firstName = Auth.user.name ? Auth.user.name.split(' ')[0] : 'Estudiante';
  $('#greeting').textContent = `¡Hola, ${firstName}! Vas por el Módulo ${currentActiveModule}`;
  $('#greeting-sub').textContent = `Llevas ${doneLessons} de ${totalLessons} lecciones. ${totalLessons - doneLessons > 0 ? 'Completa una lección más para avanzar al siguiente hito.' : '¡Has completado todas las lecciones disponibles!'}`;
}

/* ---------------- Insignias Temáticas (RF-17) ---------------- */
async function loadBadges() {
  const { data } = await api('/me/badges');

  const obtained = data.obtained || [];
  const locked = data.locked || [];
  const totalBadges = obtained.length + locked.length || 6;

  $('#top-badges-count').textContent = `${obtained.length} de ${totalBadges} insignias`;

  // Banner superior de celebración
  const celebrationWrap = $('#celebration-banner-wrap');
  if (celebrationWrap) {
    if (obtained.length > 0) {
      const latest = obtained[obtained.length - 1];
      const config = BADGE_CONFIG[latest.slug] || {
        icon: 'bi-award-fill',
        color: '#D97706',
        bgColor: '#FEF3C7',
        border: '#FDE68A'
      };

      celebrationWrap.innerHTML = `
        <div class="celebration-banner p-3 p-md-4 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="celebration-icon shadow-sm" style="background:${config.bgColor};color:${config.color};border-color:${config.border};">
              <i class="bi ${config.icon}"></i>
            </div>
            <div>
              <small class="text-uppercase fw-bold" style="color:#B45309;letter-spacing:.05em;font-size:.75rem;">Insignia Conseguida</small>
              <h3 class="fw-bold mb-0" style="font-size:1.1rem;color:#78350F;">${esc(latest.title)}</h3>
              <p class="mb-0 text-muted" style="font-size:.85rem;">${esc(latest.description)}</p>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold bg-white" onclick="window.switchStudentTab('insignias')">
              Ver mi insignia
            </button>
            <button type="button" class="btn-close" aria-label="Cerrar" onclick="this.closest('.celebration-banner').remove()"></button>
          </div>
        </div>`;
    } else {
      celebrationWrap.innerHTML = '';
    }
  }

  // 1. Widget Derecho: Mini lista de Insignias
  const miniList = $('#mini-badges-list');
  if (miniList) {
    miniList.innerHTML = '';

    const allSlugs = Object.keys(BADGE_CONFIG);
    allSlugs.forEach((slug, idx) => {
      const cfg = BADGE_CONFIG[slug];
      const isObtained = obtained.some((b) => b.slug === slug);
      const isCurrentInGame = !isObtained && (idx === obtained.length);

      const statusTag = isObtained
        ? '<span class="badge-tag-conseguida">CONSEGUIDA</span>'
        : isCurrentInGame
        ? '<span class="badge-tag-enjuego">EN JUEGO</span>'
        : '<span class="badge-tag-bloqueada">BLOQUEADA</span>';

      const rowClass = isObtained ? 'conseguida' : '';
      const iconStyle = isObtained
        ? `background:${cfg.bgColor};color:${cfg.color};border:1.5px solid ${cfg.border};`
        : isCurrentInGame
        ? `background:#EDE9FE;color:#6D4AEF;border:1.5px solid #DDD6FE;`
        : `background:#F1EFFB;color:#948FA8;border:1.5px solid #E2DEEE;`;

      miniList.innerHTML += `
        <div class="mini-badge-row ${rowClass}">
          <div class="mini-badge-icon shadow-2xs" style="${iconStyle}">
            <i class="bi ${cfg.icon}"></i>
          </div>
          <div class="flex-grow-1" style="min-width:0;">
            <div class="d-flex justify-content-between align-items-center mb-0.5">
              <h5 class="fw-bold mb-0 text-truncate" style="font-size:.88rem;color:var(--edugt-text);">${esc(cfg.category)}</h5>
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
        color: '#6D4AEF',
        bgColor: '#EDE9FE',
        border: '#DDD6FE',
        category: 'Insignia'
      };

      gridContainer.innerHTML += `
        <div class="col-12 col-md-6 col-lg-4">
          <div class="badge-card unlocked h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge rounded-pill" style="background:${config.bgColor};color:${config.color};font-weight:700;font-size:.75rem;">
                  ${config.category}
                </span>
                <span class="pill-edugt pill-done" style="font-size:.72rem;">
                  <i class="bi bi-patch-check-fill"></i> Desbloqueada
                </span>
              </div>
              <div class="badge-icon-lg shadow-sm" style="background:${config.bgColor};color:${config.color};border:2px solid ${config.border};">
                <i class="bi ${config.icon}"></i>
              </div>
              <h4 class="fw-bold text-center mb-1" style="font-size:1.05rem;">${esc(b.title)}</h4>
              <p class="text-center mb-3" style="color:var(--edugt-muted);font-size:.85rem;line-height:1.4;">
                ${esc(b.description)}
              </p>
            </div>
            <div class="text-center pt-2 border-top" style="border-color:var(--edugt-border)!important;">
              <small class="text-success fw-bold" style="font-size:.78rem;">
                <i class="bi bi-stars"></i> ¡Completada con éxito!
              </small>
            </div>
          </div>
        </div>`;
    });

    locked.forEach((b) => {
      const config = BADGE_CONFIG[b.slug] || {
        icon: 'bi-award-fill',
        color: '#948FA8',
        bgColor: '#F1EFFB',
        border: '#E2DEEE',
        category: 'Desafío'
      };

      gridContainer.innerHTML += `
        <div class="col-12 col-md-6 col-lg-4">
          <div class="badge-card locked h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge rounded-pill bg-light text-muted" style="font-weight:600;font-size:.75rem;">
                  ${config.category}
                </span>
                <span class="pill-edugt pill-locked" style="font-size:.72rem;">
                  <i class="bi bi-lock-fill"></i> Bloqueada
                </span>
              </div>
              <div class="badge-icon-lg" style="background:#F1EFFB;color:#948FA8;border:2px dashed #D5D0E6;">
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

async function loadRankings() {
  const { data } = await api(`/rankings?scope=${rankingScope}`);
  const list = data.data || [];

  const podiumContainer = $('#podium-container');
  const rankingsContainer = $('#rankings');

  $('#ranking-scope-label').textContent = rankingScope === 'global' ? 'Ámbito Global' : 'Mi Aula';

  if (list.length > 0) {
    podiumContainer.classList.remove('d-none');
    podiumContainer.innerHTML = '';

    const top1 = list[0];
    const top2 = list[1] || null;
    const top3 = list[2] || null;

    const renderPodiumItem = (student, rankClass, medal, rankNum, colSize) => {
      if (!student) return '';
      const isMe = student.user_id === Auth.user.id;
      return `
        <div class="col-md-${colSize}">
          <div class="podium-card ${rankClass} h-100 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge rounded-pill bg-white text-dark shadow-xs fw-bold px-2.5 py-1" style="font-size:.75rem;">
                Puesto #${rankNum}
              </span>
              <span style="font-size:1.4rem;">${medal}</span>
            </div>
            <div class="podium-avatar bg-white shadow-sm">
              <span class="fw-bold" style="color:var(--edugt-primary);font-size:1.2rem;">${student.name.charAt(0).toUpperCase()}</span>
            </div>
            <h4 class="fw-bold mb-1" style="font-size:1.05rem;">
              ${esc(student.name)}${isMe ? ' <span class="badge bg-primary" style="font-size:.7rem;">Tú</span>' : ''}
            </h4>
            <div class="d-flex justify-content-center align-items-center gap-2" style="font-size:.85rem;color:var(--edugt-muted);">
              <span>Nv. ${student.xp_level}</span>
              <span>·</span>
              <span class="fw-bold text-dark">${student.xp_total} XP</span>
            </div>
          </div>
        </div>`;
    };

    let podiumHtml = '';
    if (top2) podiumHtml += renderPodiumItem(top2, 'podium-2', '🥈', 2, 4);
    if (top1) podiumHtml += renderPodiumItem(top1, 'podium-1', '🥇', 1, 4);
    if (top3) podiumHtml += renderPodiumItem(top3, 'podium-3', '🥉', 3, 4);

    podiumContainer.innerHTML = podiumHtml;
  } else {
    podiumContainer.classList.add('d-none');
  }

  const rows = list.map((r, i) => {
    const medal = ['🥇', '🥈', '🥉'][i] ?? `<span style="color:var(--edugt-muted);font-weight:700">#${r.position}</span>`;
    const me = r.user_id === Auth.user.id;

    return `
      <div class="d-flex align-items-center gap-3 border rounded-4 px-3 py-2.5 ${me ? 'fw-bold border-primary' : ''}"
           style="border-color:var(--edugt-border)!important;${me ? 'background:var(--edugt-primary-soft);' : 'background:#FFFFFF;'}">
        <div style="width:36px;text-align:center;font-size:1.15rem;">${medal}</div>
        <div class="flex-grow-1">
          <span style="font-size:.95rem;">${esc(r.name)}</span>
          ${me ? '<span class="badge bg-primary ms-2" style="font-size:.7rem;">Tú</span>' : ''}
        </div>
        <div style="color:var(--edugt-muted);font-size:.85rem;">Nivel ${r.xp_level}</div>
        <div class="fw-bold text-end" style="min-width:76px;font-size:.95rem;color:var(--edugt-primary);">
          ${r.xp_total} XP
        </div>
      </div>`;
  });

  rankingsContainer.innerHTML = rows.join('') || `
    <div class="text-center py-4">
      <i class="bi bi-people text-muted fs-2 mb-2"></i>
      <p class="mb-0 text-muted">Aún no hay puntajes registrados en este ámbito.</p>
    </div>`;
}

$('#rk-global')?.addEventListener('click', async () => {
  rankingScope = 'global';
  $('#rk-global').className = 'btn btn-edugt';
  $('#rk-classroom').className = 'btn btn-google';
  await loadRankings();
});

$('#rk-classroom')?.addEventListener('click', async () => {
  rankingScope = 'classroom';
  $('#rk-classroom').className = 'btn btn-edugt';
  $('#rk-global').className = 'btn btn-google';
  await loadRankings();
});

/* ---------------- Aulas del Estudiante (RF-19) ---------------- */
async function loadStudentClassrooms() {
  const container = $('#student-classrooms-list');
  const countBadge = $('#enrolled-classrooms-count');
  if (!container) return;

  try {
    const { data } = await api('/classrooms');
    const classrooms = data.data || [];

    if (countBadge) {
      countBadge.textContent = `${classrooms.length} ${classrooms.length === 1 ? 'aula' : 'aulas'}`;
    }

    if (classrooms.length === 0) {
      container.innerHTML = `
        <div class="text-center py-4 px-3" style="background:#FAF8FF;border-radius:16px;border:1px dashed var(--edugt-border);">
          <div class="stat-icon mx-auto mb-2" style="width:48px;height:48px;background:var(--edugt-primary-soft);color:var(--edugt-primary);font-size:1.3rem;border-radius:14px;">
            <i class="bi bi-people"></i>
          </div>
          <h4 class="h6 fw-bold mb-1">Aún no estás inscrito en ningún aula</h4>
          <p class="text-muted mb-0" style="font-size:.84rem;max-width:340px;margin:0 auto;">
            Pídele a tu docente el código de inscripción de 8 caracteres e ingrésalo en el formulario de la izquierda.
          </p>
        </div>`;
      return;
    }

    container.innerHTML = classrooms.map(c => `
      <div class="card-edugt p-3 border" style="border-color:var(--edugt-border)!important;background:#FFFFFF;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="d-flex align-items-center gap-2.5">
            <div class="stat-icon" style="width:42px;height:42px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:1.2rem;border-radius:12px;">
              <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
              <h4 class="h6 fw-bold mb-0 text-dark">${esc(c.name)}</h4>
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

        <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold" onclick="window.viewClassroomRanking()">
          <i class="bi bi-trophy me-1"></i> Ver ranking de mi aula
        </button>
      </div>
    `).join('');
  } catch (err) {
    console.error('Error cargando aulas:', err);
    container.innerHTML = `<div class="alert alert-danger py-2 mb-0" style="font-size:.85rem;">Error al cargar las aulas inscritas.</div>`;
  }
}

window.viewClassroomRanking = function () {
  window.switchStudentTab('ranking');
  $('#rk-classroom')?.click();
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
